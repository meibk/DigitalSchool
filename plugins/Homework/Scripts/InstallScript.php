<?php

include_once __DIR__ . '/BaseInstallScript.php';

class InstallScript extends BaseInstallScript
{

    public function install()
    {

        $connection = $this->getConnection();

        $connection->exec("DROP TABLE IF EXISTS `exercise`;");
        $connection->exec("DROP TABLE IF EXISTS `exercise_item`;");
        $connection->exec("DROP TABLE IF EXISTS `exercise_item_result`;");
        $connection->exec("DROP TABLE IF EXISTS `exercise_result`;");
        $connection->exec("DROP TABLE IF EXISTS `homework`;");
        $connection->exec("DROP TABLE IF EXISTS `homework_item`;");
        $connection->exec("DROP TABLE IF EXISTS `homework_item_result`;");
        $connection->exec("DROP TABLE IF EXISTS `homework_result`;");
        $connection->exec("
            CREATE TABLE `exercise` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `itemCount` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '题目数量',
              `source` enum('course','lesson') NOT NULL,
              `courseId` int(10) unsigned NOT NULL,
              `lessonId` int(10) unsigned NOT NULL,
              `difficulty` varchar(64) NOT NULL DEFAULT '''''' COMMENT '难度',
              `questionTypeRange` varchar(255) NOT NULL DEFAULT '' COMMENT '题型范围',
              `createdUserId` int(10) unsigned NOT NULL,
              `createdTime` int(10) unsigned NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        $connection->exec("
            CREATE TABLE `exercise_item` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `exerciseId` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '所属练习',
              `seq` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '题目顺序',
              `questionId` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '题目ID',
              `score` float(10,1) unsigned NOT NULL DEFAULT '0.0',
              `missScore` float(10,1) NOT NULL DEFAULT '0.0' COMMENT '漏选得分',
              `parentId` int(10) unsigned NOT NULL DEFAULT '0',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        $connection->exec("
            CREATE TABLE `exercise_item_result` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `itemId` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '练习题目ID',
              `exerciseId` int(10) unsigned NOT NULL DEFAULT '0',
              `exerciseResultId` int(10) unsigned NOT NULL,
              `questionId` int(10) unsigned NOT NULL,
              `userId` int(10) unsigned NOT NULL DEFAULT '0',
              `status` enum('none','right','partRight','wrong','noAnswer') DEFAULT 'none',
              `answer` text,
              `teacherSay` text,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        $connection->exec("
            CREATE TABLE `exercise_result` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id',
              `exerciseId` int(10) unsigned NOT NULL DEFAULT '0',
              `courseId` int(10) unsigned NOT NULL,
              `lessonId` int(10) unsigned NOT NULL,
              `userId` int(10) unsigned NOT NULL DEFAULT '0',
              `rightItemCount` int(10) unsigned NOT NULL DEFAULT '0',
              `status` enum('doing','finished') NOT NULL COMMENT '状态',
              `usedTime` int(10) unsigned NOT NULL DEFAULT '0',
              `updatedTime` int(10) unsigned NOT NULL DEFAULT '0',
              `createdTime` int(10) unsigned NOT NULL DEFAULT '0',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        $connection->exec("
            CREATE TABLE `homework` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `courseId` int(10) unsigned NOT NULL DEFAULT '0',
              `lessonId` int(10) unsigned NOT NULL DEFAULT '0',
              `description` text COMMENT '作业说明',
              `itemCount` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '题目数量',
              `createdUserId` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建人',
              `createdTime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
              `updatedUserId` int(10) unsigned NOT NULL DEFAULT '0',
              `updatedTime` int(10) unsigned NOT NULL DEFAULT '0',
              PRIMARY KEY (`id`),
              KEY `lessonId` (`lessonId`),
              KEY `courseId` (`courseId`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='作业';
        ");

        $connection->exec("
            CREATE TABLE `homework_item` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `homeworkId` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '所属作业',
              `seq` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '题目顺序',
              `questionId` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '题目ID',
              `score` float(10,1) unsigned NOT NULL DEFAULT '0.0',
              `missScore` float(10,1) NOT NULL DEFAULT '0.0' COMMENT '漏选得分',
              `parentId` int(10) unsigned NOT NULL DEFAULT '0',
              PRIMARY KEY (`id`),
              KEY `homeworkId` (`homeworkId`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
        ");

        $connection->exec("
            CREATE TABLE `homework_item_result` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `itemId` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '作业题目ID',
              `homeworkId` int(10) unsigned NOT NULL DEFAULT '0',
              `homeworkResultId` int(10) unsigned NOT NULL DEFAULT '0',
              `questionId` int(10) unsigned NOT NULL DEFAULT '0',
              `userId` int(10) unsigned NOT NULL DEFAULT '0',
              `status` enum('none','right','partRight','wrong','noAnswer') DEFAULT 'none',
              `answer` text,
              `teacherSay` text,
              PRIMARY KEY (`id`),
              KEY `homeworkResultId` (`homeworkResultId`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        $connection->exec("
            CREATE TABLE `homework_result` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id',
              `homeworkId` int(10) unsigned NOT NULL DEFAULT '0',
              `courseId` int(10) unsigned NOT NULL,
              `lessonId` int(10) unsigned NOT NULL,
              `userId` int(10) unsigned NOT NULL DEFAULT '0',
              `teacherSay` text,
              `rightItemCount` int(10) unsigned NOT NULL DEFAULT '0',
              `status` enum('doing','reviewing','finished') NOT NULL COMMENT '状态',
              `checkTeacherId` int(10) unsigned NOT NULL DEFAULT '0',
              `checkedTime` int(10) unsigned NOT NULL DEFAULT '0',
              `usedTime` int(10) unsigned NOT NULL DEFAULT '0',
              `updatedTime` int(10) unsigned NOT NULL DEFAULT '0',
              `createdTime` int(10) unsigned NOT NULL DEFAULT '0',
              PRIMARY KEY (`id`),
              KEY `homeworkId` (`homeworkId`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
        ");

    }

    private function getBlockService()
    {
      return $this->createService('Content.BlockService');
    }

}