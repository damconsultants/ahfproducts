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

namespace DamConsultants\Ahfproducts\Controller\Bynderchanges;

use DamConsultants\Ahfproducts\Helper\Data;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;

class Index extends \Magento\Framework\App\Action\Action
{

    /**
     * @var $bynderDomain
     */
    public $bynderDomain = "";

    /**
     * @var $permanent_token
     */
    public $permanent_token = "";

    /**
     * @var $by_redirecturl
     */
    public $by_redirecturl;
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
        $res_array = [
            "status" => 0,
            "data" => 0,
            "message" => "something went wrong please try again. |
            please logout and login again"
        ];
        if ($this->getRequest()->isAjax()) {
            $new_value_obj = $this->getRequest()->getPost("new_valu_array");
            $bdomain = $this->getRequest()->getPost("bdomain");
            $bynder_auth = $this->loadcredential();
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
                            'property_id' => $collection_value['property_id']
                        ];
                    }
                }
                if ($bdomain_chk_cookies == $bdomain_chk_config) {
                    $bynder_auth = [
                        "bynderDomain" => $bdomain_chk_config,
                        "redirectUri" => $this->b_datahelper->getRedirecturl(),
                        "token" => $this->b_datahelper->getPermanenToken(),
                        "new_value_obj" => $new_value_obj,
                        "collection_data_value" => $collection_data_slug_val
                    ];
                    $api_response = $this->b_datahelper->changeBynderAssetsDetails($bynder_auth);
                    $api_response = json_decode($api_response, true);
                    if (isset($api_response["status"]) && $api_response["status"] == 1) {
                        $res_array["status"] = $api_response["status"];
                        $res_array["data"] = $api_response["data"];
                        $res_array["message"] = $api_response["message"];
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
