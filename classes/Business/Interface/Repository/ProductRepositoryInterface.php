<?php

namespace ChannelEngine\Business\Interface\Repository;

interface ProductRepositoryInterface
{
    /**
     * Get all active products from PrestaShop
     *
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return array
     */
    public function getAllActiveProducts(?int $limit = null, ?int $offset = null): array;

    /**
     * Get total count of active products
     *
     * @return int
     */
    public function getActiveProductsCount(): int;

    /**
     * Get product by ID with all necessary information
     *
     * @param int $productId
     *
     * @return array|null
     */
    public function getProductById(int $productId): ?array;
}