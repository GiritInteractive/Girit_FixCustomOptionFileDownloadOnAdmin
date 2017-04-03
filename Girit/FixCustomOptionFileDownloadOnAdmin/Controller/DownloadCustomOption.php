<?php
/**
 *
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Girit\FixCustomOptionFileDownloadOnAdmin\Controller;

class DownloadCustomOption extends \Magento\Sales\Controller\Download\DownloadCustomOption
{

    /**
     * Custom options download action
     *
     * @return void|\Magento\Framework\Controller\Result\Forward
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        $quoteItemOptionId = $this->getRequest()->getParam('id');
        /** @var $option \Magento\Quote\Model\Quote\Item\Option */
        $option = $this->_objectManager->create('Magento\Quote\Model\Quote\Item\Option')->load($quoteItemOptionId);
        /** @var \Magento\Framework\Controller\Result\Forward $resultForward */
        $resultForward = $this->resultForwardFactory->create();

        if (!$option->getId()) {

            //=== Girit_FixCustomOptionFileDownloadOnAdmin addition ===//
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $select = $connection->select()
                ->from(
                    ['o' => 'sales_order_item'],
                    ['o.product_options']
                )
                ->where('o.product_options LIKE ?', "%downloadCustomOption/id/".$this->getRequest()->getParam('id')."/key/".$this->getRequest()->getParam('key')."%")
                ->limit(1);
            $result = $connection->fetchAll($select); // gives associated array, table fields as key in array.
            if(isset($result[0]["product_options"])){
                $product_options = unserialize($result[0]["product_options"]);
                if(isset($product_options["options"])){
                    foreach($product_options["options"] as $option){
                        if($option["option_type"]=="file" && isset($option["option_value"])){
                            try {
                                $info = $this->unserialize->unserialize($option["option_value"]);
                                if ($this->getRequest()->getParam('key') != $info['secret_key']) {
                                    return $resultForward->forward('noroute');
                                }
                                $this->download->downloadFile($info);
                            } catch (\Exception $e) {
                                try {
                                    $info = unserialize($option["option_value"]);
                                    if(isset($info['fullpath']) && file_exists($info['fullpath'])){
                                        header('Content-Description: File Transfer');
                                        header('Content-Type: application/octet-stream');
                                        header('Content-Disposition: attachment; filename="'.basename($info['fullpath']).'"');
                                        header('Expires: 0');
                                        header('Cache-Control: must-revalidate');
                                        header('Pragma: public');
                                        header('Content-Length: ' . filesize($info['fullpath']));
                                        readfile($info['fullpath']);
                                        exit;
                                    }
                                } catch (\Exception $e) {
                                    return $resultForward->forward('noroute');
                                }
                            }
                            break;
                        }
                    }
                }
            }
            //=========================================================//

            return $resultForward->forward('noroute');
        }

        $optionId = null;
        if (strpos($option->getCode(), AbstractType::OPTION_PREFIX) === 0) {
            $optionId = str_replace(AbstractType::OPTION_PREFIX, '', $option->getCode());
            if ((int)$optionId != $optionId) {
                $optionId = null;
            }
        }
        $productOption = null;
        if ($optionId) {
            /** @var $productOption \Magento\Catalog\Model\Product\Option */
            $productOption = $this->_objectManager->create('Magento\Catalog\Model\Product\Option')->load($optionId);
        }

        if (!$productOption || !$productOption->getId() || $productOption->getType() != 'file') {
            return $resultForward->forward('noroute');
        }

        try {
            $info = $this->unserialize->unserialize($option->getValue());
            if ($this->getRequest()->getParam('key') != $info['secret_key']) {
                return $resultForward->forward('noroute');
            }
            $this->download->downloadFile($info);
        } catch (\Exception $e) {
            return $resultForward->forward('noroute');
        }
        $this->endExecute();
    }

}
