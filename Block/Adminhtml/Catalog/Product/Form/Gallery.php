<?php
namespace DamConsultants\Ahfproducts\Block\Adminhtml\Catalog\Product\Form;

class Gallery extends \Magento\Backend\Block\Template
{
    /**
     * Block template.
     *
     * @var string
     */
    protected $_template = 'group/gallery.phtml';
    /**
     * @var Database
     */
    protected $_storeManager;
    /**
     * @var Database
     */
    protected $_bulk;
    /**
     * @var Database
     */
    protected $_registry;
    /**
     * @var Database
     */
    protected $_image;
    /**
     * @var Database
     */
    protected $_helperData;
    /**
     * @var Database
     */
    protected $helper;

    /**
     * Gallery
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\ConfigurableProduct\Block\Adminhtml\Product\Steps\Bulk $bulk
     * @param \Magento\Catalog\Helper\Image $image
     * @param \Magento\Backend\Helper\Data $helperdata
     * @param \DamConsultants\Ahfproducts\Helper $helper
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Registry $registry,
        \Magento\ConfigurableProduct\Block\Adminhtml\Product\Steps\Bulk $bulk,
        \Magento\Catalog\Helper\Image $image,
        \Magento\Backend\Helper\Data $helperdata,
        \DamConsultants\Ahfproducts\Helper\Data $helper
    ) {
        $this->_storeManager = $storeManager;
        $this->_bulk = $bulk;
        $this->_registry = $registry;
        $this->_image = $image;
        $this->_helperData = $helperdata;
        $this->helper = $helper;
        parent::__construct($context);
    }
    /**
     * Get Image Roll
     *
     * @return $this
     */
    public function getBulkImageRoll()
    {
        return $this->_bulk->getMediaAttributes();
    }
    /**
     * Get Image Roll
     *
     * @return $this
     * @param string $currentProduct
     */
    public function getProduct($currentProduct)
    {
        return $this->_registry->registry($currentProduct);
    }
    /**
     * Get Bynder Domain
     *
     * @return $this
     */
    public function getBynderDomain()
    {
        return $this->helper->getBynderDomain();
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
    /**
     * Image.
     *
     * @return $this
     */
    public function getDrag()
    {
        return $this->getViewFileUrl('DamConsultants_Ahfproducts::images/drag.png');
    }
    /**
     * Image.
     *
     * @return $this
     */
    public function getDelete()
    {
        return $this->getViewFileUrl('DamConsultants_Ahfproducts::images/delete_.avif');
    }
    /**
     * Json.
     *
     * @return $this
     * @param array $attr
     */
    public function getJson($attr)
    {
        return json_encode($attr);
    }
	/**
     * Return ajax url for custom button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('bynder/index/adddatas');
    }
	/**
     * Return ajax url for custom button
     *
     * @return string
     */
    public function getAjaxImportUrl()
    {
        return $this->getUrl('bynder/index/importimages');
    }
}
