<?php

namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Math\Random; // Import Magento Random class

class Pallsku extends Action implements HttpPostActionInterface
{
    protected $resultJsonFactory;
    protected $resource;
    protected $mathRandom; // Define Math Random

    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        ResourceConnection $resource,
        Random $mathRandom // Inject Math Random
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->resource = $resource;
        $this->mathRandom = $mathRandom;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $product_sku = $this->getRequest()->getParam('product_sku');
        $select_attribute = $this->getRequest()->getParam('select_attribute');
        $select_store = $this->getRequest()->getParam('select_store');

        try {
            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('bynder_update_sku');

            if (!empty($product_sku)) {
                $numericToken = $this->mathRandom->getRandomNumber(10000000, 99999999);
                $productSku = explode(",", trim($product_sku));
                foreach ($productSku as $sku) {
                    // Check if the SKU with same attribute and store already exists
                    $selectQuery = $connection->select()
                        ->from($table, ['sku'])
                        ->where('sku = ?', $sku)
                        ->where('select_attribute = ?', $select_attribute)
                        ->where('select_store = ?', $select_store);

                    $existingSku = $connection->fetchOne($selectQuery);

                    if (!$existingSku) {
                        // Generate a unique random token (32 characters long)
                        //$token = $this->mathRandom->getRandomString(8);

                        // Insert only if SKU does not exist
                        $connection->insert($table, [
                            'sku' => $sku,
                            'select_attribute' => $select_attribute,
                            'select_store' => $select_store,
                            'status' => 'pending',
                            'token' => $numericToken // Store the generated token
                        ]);
                    }
                }
				return $result->setData(['status' => 1, 'message' => 'SKUs added to queue Please Copy This '. $numericToken. ' Token' ]);
            } else {
				$result_data = $result->setData(['status' => 0, 'message' => 'Please enter atleast 21 or more SKUs.']);
				return $result_data;
			}
        } catch (\Exception $e) {
            return $result->setData(['success' => 0, 'message' => $e->getMessage()]);
        }
    }
}
