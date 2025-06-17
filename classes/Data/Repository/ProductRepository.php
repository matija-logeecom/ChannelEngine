<?php

namespace ChannelEngine\Data\Repository;

use ChannelEngine\Business\Interface\ProductRepositoryInterface;
use Context;
use Product;
use Manufacturer;
use Validate;

class ProductRepository implements ProductRepositoryInterface
{
    private int $languageId;
    private int $shopId;

    public function __construct()
    {
        $context = Context::getContext();
        $this->languageId = (int)$context->language->id;
        $this->shopId = (int)$context->shop->id;
    }

    /**
     * @inheritDoc
     */
    public function getAllActiveProducts(?int $limit = null, ?int $offset = null): array
    {
        $productIds = Product::getProducts(
            $this->languageId,
            $offset ?? 0,
            $limit ?? 0,
            'id_product',
            'ASC',
            false,
            true
        );

        $products = [];

        foreach ($productIds as $productData) {
            $productId = (int)$productData['id_product'];
            $product = new Product($productId, false, $this->languageId, $this->shopId);

            if (!Validate::isLoadedObject($product)) {
                continue;
            }

            $manufacturerName = '';
            if ($product->id_manufacturer) {
                $manufacturer = new Manufacturer($product->id_manufacturer, $this->languageId);
                if (Validate::isLoadedObject($manufacturer)) {
                    $manufacturerName = $manufacturer->name;
                }
            }

            $products[] = [
                'id_product' => $product->id,
                'reference' => $product->reference,
                'name' => $product->name,
                'description' => $product->description,
                'description_short' => $product->description_short,
                'manufacturer_name' => $manufacturerName,
                'price' => $this->getProductFinalPrice($product->id)
            ];
        }

        return $products;
    }

    /**
     * @inheritDoc
     */
    public function getActiveProductsCount(): int
    {
        $products = Product::getProducts(
            $this->languageId,
            0,
            0,
            'id_product',
            'ASC',
            false,
            true
        );

        return count($products);
    }

    /**
     * @inheritDoc
     */
    public function getProductById(int $productId): ?array
    {
        $product = new Product($productId, false, $this->languageId, $this->shopId);

        if (!Validate::isLoadedObject($product) || !$product->active) {
            return null;
        }

        $manufacturerName = '';
        if ($product->id_manufacturer) {
            $manufacturer = new Manufacturer($product->id_manufacturer, $this->languageId);
            if (Validate::isLoadedObject($manufacturer)) {
                $manufacturerName = $manufacturer->name;
            }
        }

        return [
            'id_product' => $product->id,
            'reference' => $product->reference,
            'name' => $product->name,
            'description' => $product->description,
            'description_short' => $product->description_short,
            'manufacturer_name' => $manufacturerName,
            'price' => $this->getProductFinalPrice($product->id)
        ];
    }

    /**
     * Get the final price of a product including tax and specific prices
     *
     * @param int $productId
     * @return float
     */
    private function getProductFinalPrice(int $productId): float
    {
        $specificPriceOutput = null;

        try {
            $price = Product::getPriceStatic(
                $productId,
                true,
                null,
                6,
                null,
                false,
                true,
                1,
                false,
                null,
                null,
                null,
                $specificPriceOutput,
                true,
                true,
                Context::getContext()
            );

            return (float)$price;
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}