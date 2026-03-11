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
namespace DamConsultants\Ahfproducts\Controller\BynderIndex;

use DamConsultants\Ahfproducts\Helper\Data;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var $b_datahelper
     */
    protected $b_datahelper;
    /**
     * @var $file
     */
    protected $file;
    /**
     * @var $driverFile
     */
    protected $driverFile;
    
    /**
     * Index.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Filesystem\Io\File $file
     * @param \Magento\Framework\Filesystem\Driver\File $driverFile
     * @param Data $bynderData
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Framework\Filesystem\Driver\File $driverFile,
        Data $bynderData
    ) {
        $this->b_datahelper = $bynderData;
        $this->file = $file;
        $this->driverFile = $driverFile;
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
        $img_data_post = $this->getRequest()->getPost("img_data");
        $dir_path_post = $this->getRequest()->getPost("dir_path");
        if ($this->getRequest()->isAjax()) {
            if (isset($img_data_post) && count($img_data_post) > 0) {
                if (isset($dir_path_post) && !empty($dir_path_post)) {
                    $img_dir = BP . '/pub/media/wysiwyg/' . $dir_path_post;
                    if (!$this->file->fileExists($img_dir)) {
                        $this->file->mkdir($img_dir, 0755, true);
                    }
                    $cookie_array = $img_data_post;
                    foreach ($cookie_array as $item) {
                        $item_url = trim($item);
                        if (!empty($item_url)) {
                            $fileInfo = $this->file->getPathInfo($item_url);
                            $basename = $fileInfo['basename'];
                            $file_name = explode("?", $basename);
                            $file_name = $file_name[0];
                            $file_name = str_replace("%20", " ", $file_name);
                            $img_url = $img_dir . "/" . $file_name;
                            $this->file->write(
                                $img_url,
                                $this->driverFile->fileGetContents($item_url)
                            );
                        }
                    }
                    $res_array["status"] = 1;
                    $res_array["message"] = "successfull ";
                } else {
                    $res_array["message"] = "Something went wrong.
                    Please reload the page and try again.";
                }
            } else {
                $res_array["message"] = "Sorry,
                you not selected any item ?.
                Please select item and try again";
            }
        }
        $json_data = json_encode($res_array);
        return $this->getResponse()->setBody($json_data);
    }
    /**
     * Local Credential
     *
     * @return $this
     */
    public function loadcredential()
    {
        $this->b_datahelper->getLoadCredential();
    }
}
