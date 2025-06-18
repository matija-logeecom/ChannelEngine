<?php

namespace ChannelEngine\Business\DTO;

class Product
{
    private string $merchantProductNo;
    private string $name;
    private string $description;
    private string $brand;
    private float $price;
    private int $stock;
    private string $imageUrl;

    public function __construct(
        string $merchantProductNo,
        string $name,
        string $description,
        string $brand,
        float $price,
        int $stock,
        string $imageUrl
    )
    {
        $this->merchantProductNo = $merchantProductNo;
        $this->name = $name;
        $this->description = $description;
        $this->brand = $brand;
        $this->price = $price;
        $this->stock = $stock;
        $this->imageUrl = $imageUrl;
    }

    /**
     * Convert DTO to array for API request
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'MerchantProductNo' => $this->merchantProductNo,
            'Name' => $this->name,
            'Description' => $this->description,
            'Brand' => $this->brand,
            'Price' => $this->price,
            'Stock' => $this->stock,
            'ImageUrl' => $this->imageUrl
        ];
    }

    /**
     * Create Product DTO from PrestaShop product array
     *
     * @param array $prestashopProduct
     *
     * @return self
     */
    public static function fromPrestashopProduct(array $prestashopProduct): self
    {
        $merchantProductNo = (string)($prestashopProduct['id_product'] ?? '');

        return new self(
            $merchantProductNo,
            $prestashopProduct['name'] ?? '',
            strip_tags($prestashopProduct['description'] ?? ''),
            $prestashopProduct['manufacturer_name'] ?? '',
            (float)($prestashopProduct['price'] ?? 0),
            (int)($prestashopProduct['stock_quantity'] ?? 0),
            $prestashopProduct['image_url'] ?? ''
        );
    }

    /**
     * @return string
     */
    public function getMerchantProductNo(): string
    {
        return $this->merchantProductNo;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getBrand(): string
    {
        return $this->brand;
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @return int
     */
    public function getStock(): int
    {
        return $this->stock;
    }

    /**
     * @return string
     */
    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }
}