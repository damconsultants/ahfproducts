<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Catalog product form gallery content
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 *
 * @method \Magento\Framework\Data\Form\Element\AbstractElement getElement()
 */
namespace DamConsultants\Ahfproducts\Block\Adminhtml\Product\Helper\Form\Gallery;

use Magento\Framework\App\ObjectManager;
use Magento\Backend\Block\Media\Uploader;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Backend\Block\DataProviders\ImageUploadConfig as ImageUploadConfigDataProvider;
use Magento\MediaStorage\Helper\File\Storage\Database;
use DamConsultants\Ahfproducts\Helper\Data;

/**
 * Block for gallery content.
 */
class Content extends \Magento\Catalog\Block\Adminhtml\Product\Helper\Form\Gallery\Content
{
    /**
     * @var string
     */
    protected $_template = 'Magento_Catalog::catalog/product/helper/gallery.phtml';

    /**
     * @var \Magento\Catalog\Model\Product\Media\Config
     */
    protected $_mediaConfig;

    /**
     * @var \Magento\Framework\Json\EncoderInterface
     */
    protected $_jsonEncoder;

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    private $imageHelper;

    /**
     * @var ImageUploadConfigDataProvider
     */
    private $imageUploadConfigDataProvider;

    /**
     * @var Database
     */
    private $fileStorageDatabase;
    /**
     * @var Database
     */
    private $request;
    /**
     * @var Database
     */
    private $b_datahelper;

    /**
     * Catalog product form gallery content
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param \Magento\Catalog\Model\Product\Media\Config $mediaConfig
     * @param ImageUploadConfigDataProvider $imageUploadConfigDataProvider
     * @param Data $bynderData
     * @param \Magento\Framework\App\RequestInterface $httpRequest
     * @param Database $fileStorageDatabase
     * @param array $data
     */

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Catalog\Model\Product\Media\Config $mediaConfig,
        ImageUploadConfigDataProvider $imageUploadConfigDataProvider = null,
        Data $bynderData,
        \Magento\Framework\App\RequestInterface $httpRequest,
        Database $fileStorageDatabase = null,
        array $data = []
    ) {
        parent::__construct($context, $jsonEncoder, $mediaConfig, $data);
        $this->request = $httpRequest;
        $this->b_datahelper = $bynderData;
    }
    /**
     * Check Bynder.
     *
     * @return array
     */
    public function getcheckbynder()
    {
        $check_bynder = $this->b_datahelper->getCheckBynder();
        $array = json_decode($check_bynder, true);
        return $array;
    }
    /**
     * Get HttpData.
     *
     * @return $this
     */
    public function getHttpData()
    {
        return $this->request->getServer('HTTP_HOST');
    }
    /**
     * EntityId.
     *
     * @return $this
     */
    public function getEntityId()
    {
        return $this->getRequest()->getParam('id');
    }
}
