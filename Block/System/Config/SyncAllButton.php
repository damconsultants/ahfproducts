<?php
namespace DamConsultants\Ahfproducts\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use \Magento\Store\Model\StoreManagerInterface;

class SyncAllButton extends Field
{
    /**
     * Block template.
     *
     * @var string
     */
    protected $_template = 'DamConsultants_Ahfproducts::system/config/syncallbutton.phtml';
    /**
     * @var $_storeManager
     */
    protected $_storeManager;
    /**
     * @var $HelperBackend
     */
    protected $HelperBackend;
    /**
     * @var $_toHtml
     */
    protected $_toHtml;
    /**
     * @var $getUrl
     */
    protected $getUrl;

    /**
     * Sync Button
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param \Magento\Backend\Helper\Data $HelperBackend
     * @param array $data
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        \Magento\Backend\Helper\Data $HelperBackend,
        array $data = []
    ) {
        $this->_storeManager = $storeManager;
        $this->HelperBackend = $HelperBackend;
        parent::__construct($context, $data);
    }

    /**
     * Render
     *
     * @return $this
     * @param object $element
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }
    /**
     * Return get Elemrent Html
     *
     * @return string
     * @param object $element
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }
    /**
     * Return ajax url for custom button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('bynder/index/pallsku');
    }

    /**
     * Get Button
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()
        ->createBlock(\Magento\Backend\Block\Widget\Button::class)
        ->setData(
            [
                'id' => 'bt_id_3',
                'label' => __('Sync All Sku'),
            ]
        );

        return $button->toHtml();
    }
}
