<?php

namespace Homework\Service\Homework\Dao;

interface ExerciseItemDao
{
    public function getItem($id);

    public function addItem($items);

    public function deleteItem($id);

    public function deleteItemByExerciseId($exerciseId);

    public function findItemsByExerciseId($exerciseId);
}