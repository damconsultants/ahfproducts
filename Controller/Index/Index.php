<?php

/**
 * DamConsultants
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 *  DamConsultants_Ahfproducts
 */

namespace DamConsultants\Ahfproducts\Controller\Index;

use DamConsultants\Ahfproducts\Helper\Data;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var $b_datahelper
     */
    protected $b_datahelper;
    /**
     * @var $metaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;

    /**
     * Index
     * @param \Magento\Framework\App\Action\Context $context
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param Data $bynderData
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        Data $bynderData
    ) {
        $this->b_datahelper = $bynderData;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        return parent::__construct($context);
    }
    /**
     * Execute
     *
     * @return $this
     */
    public function execute()
    {
        $databaseId = $this->getRequest()->getPost("databaseId");
        $datasetType = $this->getRequest()->getPost("datasetType");
        $bdomain = $this->getRequest()->getPost("bdomain");
        if ($this->getRequest()->isAjax()) {
            if (isset($databaseId) && count($databaseId) > 0
                && isset($datasetType) && count($datasetType) > 0
                && isset($bdomain) && !empty($bdomain)
            ) {
                $og_media_ids = $databaseId;
                $dataset_types = $datasetType;
                $bdomain = (string) $bdomain;
                $bynder_auth = $this->loadcredential();
                if ($bynder_auth == 1) {
                    $bdomain_chk_cookies = str_replace("https://", "", $bdomain);
                    $bdomain_chk_config = str_replace(
                        "https://",
                        "",
                        $this->b_datahelper->getBynderDom()
                    );
                    
                    $collection_data_value = [];
                    $collection = $this->metaPropertyCollectionFactory->create()->getData();
                    if (count($collection) >= 1) {
                        foreach ($collection as $key => $collection_value) {
                            $collection_data_value[] = [
                                'id' => $collection_value['id'],
                                'property_name' => $collection_value['property_name'],
                                'property_id' => $collection_value['property_id'],
                                'magento_attribute' => $collection_value['magento_attribute'],
                                'attribute_id' => $collection_value['attribute_id'],
                                'bynder_property_slug' => $collection_value['bynder_property_slug'],
                                'system_slug' => $collection_value['system_slug'],
                                'system_name' => $collection_value['system_name']
                            ];
                            $collection_data_slug_val[$collection_value['system_slug']] = [
                                'bynder_property_slug' => $collection_value['bynder_property_slug'],
                            ];
                        }

                    }
                    if ($bdomain_chk_cookies == $bdomain_chk_config) {
                        $bynder_auth = [
                            "bynderDomain" => $bdomain_chk_config,
                            "redirectUri" => $this->b_datahelper->getRedirecturl(),
                            "token" => $this->b_datahelper->getPermanenToken(),
                            "og_media_ids" => $og_media_ids,
                            "dataset_types" => $dataset_types,
                            "collection_data_value" => $collection_data_value
                        ];
                        $api_response = $this->b_datahelper->getDerivativesImage($bynder_auth);
                        $api_response = json_decode($api_response, true);
                        if (isset($api_response["status"]) && $api_response["status"] == 1) {
                            $res_array["status"] = $api_response["status"];
                            $res_array["data"] = $api_response["data"];
                            $res_array["message"] = $api_response["message"];
                            $res_array["bynder_auth"] = $bynder_auth;
                        } else {
                            $res_array["data"] = $api_response;
                            $res_array["message"] = $api_response["message"];
                        }
                    } else {
                        $res_array["message"]="Please Check Your Entered Bynder Domain | Please Check Your Credentials";
                    }
                } else {
                    $res_array["message"] = $bynder_auth;
                }
            } else {
                $res_array[
                    "message"
                    ]="Please check your credentials | session has expired. please logout and login again";
            }
        }

        $json_data = json_encode($res_array);
        return $this->getResponse()->setBody($json_data);
    }
    /**
     * Useing Helper
     *
     * @return getLoadCredential
     */
    public function loadcredential()
    {
        return $this->b_datahelper->getLoadCredential();
    }
}
