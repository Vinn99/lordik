<?php
/**
 * VacancyModule_alias.php
 * Backward compatibility: VacancyModule → VacancyModel
 */
if (!class_exists('VacancyModule')) class_alias('VacancyModel', 'VacancyModule');
