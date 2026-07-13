<?php

/**
 * DamConsultants
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  DamConsultants
 * @package   DamConsultants_Ahfproducts
 *
 */

namespace DamConsultants\Ahfproducts\Block\Product\View;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ImagesConfigFactoryInterface;
use Magento\Catalog\Model\Product\Image\UrlBuilder;
use Magento\Framework\Data\Collection;
use Magento\Framework\DataObject;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Stdlib\ArrayUtils;

class Gallery extends \Magento\Catalog\Block\Product\View\Gallery
{
    /**
     * @var \Magento\Framework\Config\View
     */
    protected $configView;

    /**
     * @var EncoderInterface
     */
    protected $jsonEncoder;

    /**
     * @var array
     */
    private $galleryImagesConfig;

    /**
     * @var ImagesConfigFactoryInterface
     */
    private $galleryImagesConfigFactory;

    /**
     * @var UrlBuilder
     */
    private $imageUrlBuilder;
    
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    public $_request;
    
    /**
     * @var _registry
     */
    protected $_registry;

    /**
     * Gallery
     * @param \Magento\Framework\App\Request\Http $request
     * @param Context $context
     * @param ArrayUtils $arrayUtils
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param \Magento\Framework\Registry $Registry
     * @param ImagesConfigFactoryInterface $imagesConfigFactory
     * @param array $galleryImagesConfig
     * @param UrlBuilder $urlBuilder
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        Context $context,
        ArrayUtils $arrayUtils,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        \Magento\Framework\Registry $Registry,
        ?ImagesConfigFactoryInterface $imagesConfigFactory = null,
        array $galleryImagesConfig = [],
        ?UrlBuilder $urlBuilder = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $arrayUtils,
            $jsonEncoder,
            $data,
            $imagesConfigFactory,
            $galleryImagesConfig,
            $urlBuilder
        );
        $this->jsonEncoder = $jsonEncoder;
        $this->galleryImagesConfigFactory = $imagesConfigFactory ?: ObjectManager::getInstance()
            ->get(ImagesConfigFactoryInterface::class);
        $this->galleryImagesConfig = $galleryImagesConfig;
        $this->imageUrlBuilder = $urlBuilder ?? ObjectManager::getInstance()->get(UrlBuilder::class);
        $this->_registry = $Registry;
        $this->_request = $request;
    }

    /**
     * Retrieve collection of gallery images
     *
     * @return Collection
     */
    public function getGalleryImages()
    {
        $product = $this->getProduct();
        $images = $product->getMediaGalleryImages();
        if (!$images instanceof \Magento\Framework\Data\Collection) {
            return $images;
        }

        foreach ($images as $image) {
            $galleryImagesConfig = $this->getGalleryImagesConfig()->getItems();
            foreach ($galleryImagesConfig as $imageConfig) {
                $image->setData(
                    $imageConfig->getData('data_object_key'),
                    $this->imageUrlBuilder->getUrl($image->getFile(), $imageConfig['image_id'])
                );
            }
        }

        return $images;
    }

    /**
     * Return magnifier options
     *
     * @return string
     */
    public function getMagnifier()
    {
        return $this->jsonEncoder->encode($this->getVar('magnifier'));
    }

    /**
     * Return breakpoints options
     *
     * @return string
     */
    public function getBreakpoints()
    {
        return $this->jsonEncoder->encode($this->getVar('breakpoints'));
    }

    /**
     * Check if image has a specific role
     *
     * @param array $imageRoles
     * @param string $role
     * @return bool
     */
    private function hasImageRole($imageRoles, $role)
    {
        if (!is_array($imageRoles)) {
            return false;
        }
        return in_array($role, $imageRoles);
    }

    /**
     * Process Bynder images
     *
     * @param array $bynderImages
     * @param string $mainImageRole
     * @return array
     */
    private function processBynderImages($bynderImages, $mainImageRole = 'Base')
    {
        $imagesItems = [];
        
        if (empty($bynderImages) || !is_array($bynderImages)) {
            return $imagesItems;
        }

        // Sort by is_order
        usort($bynderImages, function ($a, $b) {
            return (int)$a['is_order'] <=> (int)$b['is_order'];
        });

        foreach ($bynderImages as $values) {
            $imageUrl = trim($values['thum_url']);
            $isMain = false;

            // Check if this is the main image based on role
            if ($values['item_type'] === 'IMAGE' && isset($values['image_role'])) {
                $isMain = $this->hasImageRole($values['image_role'], $mainImageRole);
            }

            $imageItem = new DataObject([
                'thumb' => $imageUrl,
                'img' => $imageUrl,
                'full' => $imageUrl,
                'caption' => $values['alt_text'] ?? $this->getProduct()->getName(),
                'position' => (int)$values['is_order'],
                'isMain' => $isMain,
                'type' => ($values['item_type'] === 'IMAGE') ? 'image' : 'video',
                'videoUrl' => ($values['item_type'] === 'VIDEO') ? $values['item_url'] : null,
                'src' => ($values['item_type'] === 'VIDEO') ? $values['item_url'] : null,
                'type' => ($values['item_type'] === 'VIDEO') ? 'iframe' : 'image'
            ]);
            
            $imagesItems[] = $imageItem->toArray();
        }

        return $imagesItems;
    }

    /**
     * Process default Magento gallery images
     *
     * @return array
     */
    private function processDefaultGalleryImages()
    {
        $imagesItems = [];
        
        foreach ($this->getGalleryImages() as $image) {
            $imageItem = new DataObject([
                'thumb' => $image->getData('small_image_url'),
                'img' => $image->getData('medium_image_url'),
                'full' => $image->getData('large_image_url'),
                'caption' => $image->getLabel() ?: $this->getProduct()->getName(),
                'position' => $image->getData('position'),
                'isMain' => $this->isMainImage($image),
                'type' => str_replace('external-', '', $image->getMediaType()),
                'videoUrl' => $image->getVideoUrl(),
            ]);
            
            foreach ($this->getGalleryImagesConfig()->getItems() as $imageConfig) {
                $imageItem->setData(
                    $imageConfig->getData('json_object_key'),
                    $image->getData($imageConfig->getData('data_object_key'))
                );
            }
            $imagesItems[] = $imageItem->toArray();
        }
        
        return $imagesItems;
    }

    /**
     * Retrieve product images in JSON format
     *
     * @return string
     */
    public function getGalleryImagesJson()
    {
        $product = $this->_registry->registry('product');
        $useBynderCdn = (int)$product->getData('use_bynder_cdn');
        $useBynderBothImage = (int)$product->getData('use_bynder_both_image');
        
        $imagesItems = [];
        $bynderImages = $product->getData('bynder_multi_img');
        $bynderImageData = !empty($bynderImages) ? json_decode($bynderImages, true) : [];

        // Handle Both Image case
        if ($useBynderBothImage === 1) {
            // Add Bynder images first
            if (!empty($bynderImageData) && is_array($bynderImageData)) {
                $bynderItems = $this->processBynderImages($bynderImageData, 'image');
                $imagesItems = array_merge($imagesItems, $bynderItems);
            }
            
            // Add default gallery images
            $defaultItems = $this->processDefaultGalleryImages();
            $imagesItems = array_merge($imagesItems, $defaultItems);
            
        } 
        // Handle CDN Only case
        elseif ($useBynderCdn === 1) {
            if (!empty($bynderImageData) && is_array($bynderImageData)) {
                $imagesItems = $this->processBynderImages($bynderImageData, 'Base');
            } else {
                // Fallback to default gallery if CDN empty
                $imagesItems = $this->processDefaultGalleryImages();
            }
        } 
        // Default case - use standard gallery
        else {
            $imagesItems = $this->processDefaultGalleryImages();
        }

        return json_encode($imagesItems);
    }

    /**
     * Retrieve gallery url
     *
     * @param null|\Magento\Framework\DataObject $image
     * @return string
     */
    public function getGalleryUrl($image = null)
    {
        $params = ['id' => $this->getProduct()->getId()];
        if ($image) {
            $params['image'] = $image->getValueId();
        }
        return $this->getUrl('catalog/product/gallery', $params);
    }

    /**
     * Is product main image
     *
     * @param \Magento\Framework\DataObject $image
     * @return bool
     */
    public function isMainImage($image)
    {
        $product = $this->getProduct();
        return $product->getImage() == $image->getFile();
    }

    /**
     * Returns image attribute
     *
     * @param string $imageId
     * @param string $attributeName
     * @param string $default
     * @return string
     */
    public function getImageAttribute($imageId, $attributeName, $default = null)
    {
        $attributes = $this->getConfigView()
            ->getMediaAttributes('Magento_Catalog', Image::MEDIA_TYPE_CONFIG_NODE, $imageId);
        return $attributes[$attributeName] ?? $default;
    }

    /**
     * Retrieve config view
     *
     * @return \Magento\Framework\Config\View
     */
    private function getConfigView()
    {
        if (!$this->configView) {
            $this->configView = $this->_viewConfig->getViewConfig();
        }
        return $this->configView;
    }

    /**
     * Returns image gallery config object
     *
     * @return Collection
     */
    private function getGalleryImagesConfig()
    {
        if (false === $this->hasData('gallery_images_config')) {
            $galleryImageConfig = $this->galleryImagesConfigFactory->create($this->galleryImagesConfig);
            $this->setData('gallery_images_config', $galleryImageConfig);
        }

        return $this->getData('gallery_images_config');
    }
}