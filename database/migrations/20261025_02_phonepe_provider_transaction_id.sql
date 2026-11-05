USE `gym_management`;

ALTER TABLE `phonepe_orders`
    ADD COLUMN `provider_transaction_id` VARCHAR(100) DEFAULT NULL AFTER `phonepe_order_id`,
    ADD UNIQUE KEY `uq_phonepe_provider_transaction` (`provider_transaction_id`);
