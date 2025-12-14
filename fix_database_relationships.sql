-- ============================================
-- Database Relationships Fix Script
-- Thesis Panel Scheduling System
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. Drop existing foreign keys (if any)
-- ============================================
-- Note: These may fail if they don't exist, that's OK

-- Try to drop existing FKs on assignment
ALTER TABLE `assignment` DROP FOREIGN KEY IF EXISTS `fk_assignment_panelist`;
ALTER TABLE `assignment` DROP FOREIGN KEY IF EXISTS `fk_assignment_group`;
ALTER TABLE `assignment` DROP FOREIGN KEY IF EXISTS `fk_assignment_schedule`;

-- Try to drop existing FKs on schedule
ALTER TABLE `schedule` DROP FOREIGN KEY IF EXISTS `fk_schedule_group`;

-- Try to drop existing FKs on availability
ALTER TABLE `availability` DROP FOREIGN KEY IF EXISTS `fk_availability_panelist`;

-- Try to drop existing FKs on thesis
ALTER TABLE `thesis` DROP FOREIGN KEY IF EXISTS `fk_thesis_group`;
ALTER TABLE `thesis` DROP FOREIGN KEY IF EXISTS `fk_thesis_adviser`;

-- Try to drop existing FKs on evaluation
ALTER TABLE `evaluation` DROP FOREIGN KEY IF EXISTS `fk_evaluation_panelist`;
ALTER TABLE `evaluation` DROP FOREIGN KEY IF EXISTS `fk_evaluation_group`;

-- ============================================
-- 2. Add indexes on foreign key columns
-- ============================================

-- Assignment table indexes
CREATE INDEX IF NOT EXISTS `idx_assignment_panelist` ON `assignment` (`panelist_id`);
CREATE INDEX IF NOT EXISTS `idx_assignment_group` ON `assignment` (`group_id`);
CREATE INDEX IF NOT EXISTS `idx_assignment_schedule` ON `assignment` (`schedule_id`);

-- Schedule table indexes
CREATE INDEX IF NOT EXISTS `idx_schedule_group` ON `schedule` (`group_id`);

-- Availability table indexes  
CREATE INDEX IF NOT EXISTS `idx_availability_panelist` ON `availability` (`panelist_id`);

-- Thesis table indexes
CREATE INDEX IF NOT EXISTS `idx_thesis_group` ON `thesis` (`group_id`);
CREATE INDEX IF NOT EXISTS `idx_thesis_adviser` ON `thesis` (`adviser_id`);

-- Evaluation table indexes
CREATE INDEX IF NOT EXISTS `idx_evaluation_panelist` ON `evaluation` (`panelist_id`);
CREATE INDEX IF NOT EXISTS `idx_evaluation_group` ON `evaluation` (`group_id`);

-- Notifications table indexes
CREATE INDEX IF NOT EXISTS `idx_notifications_user` ON `notifications` (`user_id`);

-- ============================================
-- 3. Add Foreign Key Constraints
-- ============================================

-- Assignment -> Panelist
ALTER TABLE `assignment`
    ADD CONSTRAINT `fk_assignment_panelist`
    FOREIGN KEY (`panelist_id`) REFERENCES `panelist`(`panelist_id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Assignment -> Thesis Group
ALTER TABLE `assignment`
    ADD CONSTRAINT `fk_assignment_group`
    FOREIGN KEY (`group_id`) REFERENCES `thesis_group`(`group_id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Assignment -> Schedule
ALTER TABLE `assignment`
    ADD CONSTRAINT `fk_assignment_schedule`
    FOREIGN KEY (`schedule_id`) REFERENCES `schedule`(`schedule_id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Schedule -> Thesis Group
ALTER TABLE `schedule`
    ADD CONSTRAINT `fk_schedule_group`
    FOREIGN KEY (`group_id`) REFERENCES `thesis_group`(`group_id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Availability -> Panelist
ALTER TABLE `availability`
    ADD CONSTRAINT `fk_availability_panelist`
    FOREIGN KEY (`panelist_id`) REFERENCES `panelist`(`panelist_id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Thesis -> Thesis Group
ALTER TABLE `thesis`
    ADD CONSTRAINT `fk_thesis_group`
    FOREIGN KEY (`group_id`) REFERENCES `thesis_group`(`group_id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Thesis -> Adviser (Panelist)
ALTER TABLE `thesis`
    ADD CONSTRAINT `fk_thesis_adviser`
    FOREIGN KEY (`adviser_id`) REFERENCES `panelist`(`panelist_id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Evaluation -> Panelist
ALTER TABLE `evaluation`
    ADD CONSTRAINT `fk_evaluation_panelist`
    FOREIGN KEY (`panelist_id`) REFERENCES `panelist`(`panelist_id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Evaluation -> Thesis Group
ALTER TABLE `evaluation`
    ADD CONSTRAINT `fk_evaluation_group`
    FOREIGN KEY (`group_id`) REFERENCES `thesis_group`(`group_id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Database relationships have been fixed!' as Message;
