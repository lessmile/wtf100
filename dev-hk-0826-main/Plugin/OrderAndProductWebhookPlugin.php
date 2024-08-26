<?php
namespace Vendor\Module\Plugin;

use Magento\Sales\Model\Order;
use Magento\Catalog\Model\Product;
use Magento\Framework\HTTP\Client\Curl;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableType;
use Magento\Bundle\Model\ResourceModel\Selection as BundleSelection;

class OrderAndProductWebhookPlugin
{
    protected $curl;
    protected $configurableType;
    protected $bundleSelection;

    public function __construct(
        Curl $curl,
        ConfigurableType $configurableType,
        BundleSelection $bundleSelection
    ) {
        $this->curl = $curl;
        $this->configurableType = $configurableType;
        $this->bundleSelection = $bundleSelection;
    }

    public function afterSave($subject, $result)
    {
        $webhookUrl = 'https://enkil31xdetyg.x.pipedream.net/magento/dev/webhooks';

        // 针对订单对象的逻辑
        if ($subject instanceof Order) {
            if ($subject->getIsObjectNew() || $subject->hasDataChanges()) {
                $data = [
                    'type' => 'order',
                    'order_id' => $subject->getId(),
                    'status' => $subject->getStatus()
                ];
                $this->curl->post($webhookUrl, json_encode($data));
            }
        }

        // 针对商品对象的逻辑
        if ($subject instanceof Product) {
            if ($subject->getIsObjectNew() || $subject->hasDataChanges()) {
                $productType = $subject->getTypeId(); // 获取商品类型
                $isParentProduct = false;

                // 判断是否为主商品的逻辑
                if ($productType === 'configurable' || $productType === 'grouped' || $productType === 'bundle') {
                    $isParentProduct = true; // 这些类型直接是主商品
                } elseif ($productType === 'simple') {
                    // 检查 simple 商品是否为某个 configurable 或 bundle 商品的子商品
                    $isChildOfConfigurable = $this->isChildOfConfigurable($subject);
                    $isChildOfBundle = $this->isChildOfBundle($subject);
                    $isParentProduct = !$isChildOfConfigurable && !$isChildOfBundle; // 如果不是子商品，则为主商品
                }

                $data = [
                    'type' => 'product',
                    'product_id' => $subject->getId(),
                    'sku' => $subject->getSku(),
                    'product_type' => $productType,
                    'is_parent_product' => $isParentProduct // 是否为主商品
                ];

                $this->curl->post($webhookUrl, json_encode($data));
            }
        }

        return $result;
    }

    // 检查 simple 商品是否为某个 configurable 商品的子商品
    protected function isChildOfConfigurable(Product $product)
    {
        $parentIds = $this->configurableType->getParentIdsByChild($product->getId());
        return !empty($parentIds);
    }

    // 检查 simple 商品是否为某个 bundle 商品的子商品
    protected function isChildOfBundle(Product $product)
    {
        $parentIds = $this->bundleSelection->getParentIdsByChild($product->getId());
        return !empty($parentIds);
    }
}
