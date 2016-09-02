<?php

namespace Homework\Service\Homework\Dao;

interface ExerciseResultDao
{
	public function getExerciseResult($id);

	public function addExerciseResult($fields);

	public function deleteExerciseResult($id);

	public function updateExerciseResult($id,array $fields);

	public function getExerciseResultByExerciseIdAndUserId($exerciseId, $userId);

	public function getExerciseResultByExerciseIdAndStatusAndUserId($exerciseId, $status, $userId);

	public function deleteExerciseResultByExerciseId($exerciseId);
}