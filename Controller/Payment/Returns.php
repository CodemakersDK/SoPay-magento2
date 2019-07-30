<?php

/**
 * For magento 2.3 use another callback class
 */
if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")) {
    include __DIR__ . "/ReturnsM23.php";
} else {
    include __DIR__ . "/ReturnsM22.php";
}