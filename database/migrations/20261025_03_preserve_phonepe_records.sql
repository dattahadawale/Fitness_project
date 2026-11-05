USE `gym_management`;

ALTER TABLE `phonepe_orders`
    DROP FOREIGN KEY `fk_phonepe_member`,
    DROP FOREIGN KEY `fk_phonepe_payment`,
    ADD CONSTRAINT `fk_phonepe_member_restrict` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_phonepe_payment_restrict` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
