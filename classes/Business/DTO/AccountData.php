<?php

namespace ChannelEngine\Business\DTO;

class AccountData
{
    private string $accountName;
    private string $apiKey;
    private string $companyName;
    private string $currencyCode;
    private array $settings;

    public function __construct(
        string $accountName,
        string $apiKey,
        string $companyName,
        string $currencyCode,
        array $settings = []
    ) {
        $this->accountName = $accountName;
        $this->apiKey = $apiKey;
        $this->companyName = $companyName;
        $this->currencyCode = $currencyCode;
        $this->settings = $settings;
    }

    /**
     * Convert DTO to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'account_name' => $this->accountName,
            'api_key' => $this->apiKey,
            'company_name' => $this->companyName,
            'currency_code' => $this->currencyCode,
            'settings' => $this->settings
        ];
    }

    /**
     * Convert DTO to array for public use (excluding sensitive data)
     *
     * @return array
     */
    public function toPublicArray(): array
    {
        return [
            'account_name' => $this->accountName,
            'company_name' => $this->companyName,
            'currency_code' => $this->currencyCode,
            'settings' => $this->settings
        ];
    }

    /**
     * Create AccountData DTO from array
     *
     * @param array $data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['account_name'] ?? '',
            $data['api_key'] ?? '',
            $data['company_name'] ?? '',
            $data['currency_code'] ?? '',
            $data['settings'] ?? []
        );
    }

    /**
     * Create AccountData DTO from ChannelEngine API settings response
     *
     * @param array $settings
     * @param string $accountName
     * @param string $apiKey
     *
     * @return self
     */
    public static function fromChannelEngineSettings(array $settings, string $accountName, string $apiKey): self
    {
        return new self(
            $accountName,
            $apiKey,
            $settings['CompanyName'] ?? '',
            $settings['CurrencyCode'] ?? '',
            $settings
        );
    }

    /**
     * @return string
     */
    public function getAccountName(): string
    {
        return $this->accountName;
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @return string
     */
    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    /**
     * @return string
     */
    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }
}