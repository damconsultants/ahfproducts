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

namespace DamConsultants\Ahfproducts\Controller\Redirecturl;

use Magento\Framework\App\Action\Action;

class Index extends Action
{
    /**
     * @var $b_datahelper
     */
    protected $b_datahelper;
    /**
     * @var $redirecturi
     */
    protected $redirecturi;
    /**
     * Index.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \DamConsultants\Ahfproducts\Helper\Data $bynderData
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \DamConsultants\Ahfproducts\Helper\Data $bynderData
    ) {
        $this->b_datahelper = $bynderData;
        $this->redirecturi = "bynder/redirecturl";
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
            "html" => "",
            "data" => "",
            "message" => "something went wrong. please re-login & try again",
            "_POST" => $this->getRequest()->getPost(),
            "_GET" => $this->getRequest()->getParams(),
            "getcwd" => getcwd()
        ];

        $res_array["status"] = 1;
        $res_array["message"] = "redirecturl";
        $res_array["getbaseurl"] = $this->getbaseurl();
        $json_data = json_encode($res_array);
        return $this->getResponse()->setBody($json_data);
    }
    /**
     * Get Base Url
     *
     * @return $this
     */
    public function getbaseurl()
    {
        return $this->b_datahelper->getbaseurl();
    }
    /**
     * Redirect Url
     *
     * @return $this
     */
    public function redirecturi()
    {
        return "redirecturi";
    }
}
