<?php
/**
 * MagoArab OrderEnhancer Order Name Column - FIXED VERSION
 * Gets name from Shipping Address ONLY
 *
 * @category    MagoArab
 * @package     MagoArab_OrderEnhancer
 * @author      MagoArab Team
 * @copyright   Copyright (c) 2024 MagoArab
 */

namespace MagoArab\OrderEnhancer\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class OrderName extends Column
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;
    
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger,
        array $components = [],
        array $data = []
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source - Get Order Name from Shipping Address ONLY
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $orderName = $this->getShippingName($item);
                $item[$this->getData('name')] = $orderName;
            }
        }
        
        return $dataSource;
    }
    
    /**
     * Get name from shipping address ONLY
     *
     * @param array $item
     * @return string
     */
    protected function getShippingName(array $item)
    {
        // First check if we already have the shipping name from the query
        if (!empty($item['order_name'])) {
            return $item['order_name'];
        }
        
        // Check shipping firstname and lastname fields
        $firstName = '';
        $lastName = '';
        
        if (!empty($item['shipping_firstname'])) {
            $firstName = trim($item['shipping_firstname']);
        }
        
        if (!empty($item['shipping_lastname'])) {
            $lastName = trim($item['shipping_lastname']);
        }
        
        // If we have shipping name components, use them
        if ($firstName || $lastName) {
            return trim($firstName . ' ' . $lastName);
        }
        
        // If no shipping name found, try to fetch from database
        if (!empty($item['entity_id'])) {
            $shippingName = $this->fetchShippingNameFromDb($item['entity_id']);
            if ($shippingName) {
                return $shippingName;
            }
        }
        
        // Return empty string if no name found (don't show Guest Customer in grid)
        return '';
    }
    
    /**
     * Fetch shipping name directly from database
     *
     * @param int $orderId
     * @return string|null
     */
    protected function fetchShippingNameFromDb($orderId)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('sales_order_address');
            
            $select = $connection->select()
                ->from($tableName, ['firstname', 'lastname'])
                ->where('parent_id = ?', $orderId)
                ->where('address_type = ?', 'shipping')
                ->limit(1);
            
            $result = $connection->fetchRow($select);
            
            if ($result) {
                $firstName = !empty($result['firstname']) ? trim($result['firstname']) : '';
                $lastName = !empty($result['lastname']) ? trim($result['lastname']) : '';
                
                if ($firstName || $lastName) {
                    return trim($firstName . ' ' . $lastName);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error fetching shipping name: ' . $e->getMessage());
        }
        
        return null;
    }
}