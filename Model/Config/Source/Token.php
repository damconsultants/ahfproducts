<?php

namespace DamConsultants\Ahfproducts\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Store\Model\StoreManagerInterface;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MagentoSkuCollectionFactory as TokenCollectionFactory;

class Token implements ArrayInterface
{
    protected $storeManager;
    protected $tokenCollectionFactory;

    public function __construct(
        StoreManagerInterface $storeManager,
        TokenCollectionFactory $tokenCollectionFactory
    ) {
        $this->storeManager = $storeManager;
        $this->tokenCollectionFactory = $tokenCollectionFactory;
    }

    /**
     * Return array of unique tokens from custom table
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $uniqueTokens = [];

        $tokenCollection = $this->tokenCollectionFactory->create();

        foreach ($tokenCollection as $tokenItem) {
            $token = $tokenItem->getData('token');

            // Check if the token is already added to the uniqueTokens array
            if (!in_array($token, $uniqueTokens)) {
                $uniqueTokens[] = $token; // Add token to uniqueTokens to track it
                
                $options[] = [
                    'value' => $token,
                    'label' => $token
                ];
            }
        }

        return $options;
    }
}
