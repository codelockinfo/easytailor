<?php
/**
 * Customer Model - DEPRECATED
 * Tailoring Management System
 * 
 * NOTE: This file is deprecated. Please use ../models/Customer.php instead.
 * This file redirects to the main Customer model to prevent duplicate class declarations.
 */

// Prevent duplicate class declaration
if (!class_exists('Customer', false)) {
    // Load the main Customer model instead
    require_once __DIR__ . '/../../models/Customer.php';
}
