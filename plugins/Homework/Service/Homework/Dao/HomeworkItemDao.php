<?php

namespace Homework\Service\Homework\Dao;

interface HomeworkItemDao
{
    public function getItem($id);

    public function addItem($items);

    public function deleteItem($id);

    public function findItemsByHomeworkId($homeworkId);
}