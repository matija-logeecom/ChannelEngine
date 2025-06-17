<?php

namespace ChannelEngine\Business\DTO;

class Product
{
    private string $merchantProductNo;
    private string $name;
    private string $description;
    private string $brand;
    private float $price;

    public function __construct(
        string $merchantProductNo,
        string $name,
        string $description,
        string $brand,
        float $price
    )
    {
        $this->merchantProductNo = $merchantProductNo;
        $this->name = $name;
        $this->description = $description;
        $this->brand = $brand;
        $this->price = $price;
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
            'Price' => $this->price
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
            (float)($prestashopProduct['price'] ?? 0)
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
}