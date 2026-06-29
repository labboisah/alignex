<?php

namespace App\Support;

use App\Models\Exam;

class ExamOwnershipRules
{
    /**
     * @return array<int, string>
     */
    public static function allowedCategories(string $ownerType): array
    {
        return match ($ownerType) {
            Exam::OWNER_SECONDARY_SCHOOL => [Exam::CATEGORY_TERMINAL],
            Exam::OWNER_PROFESSIONAL_SCHOOL => [Exam::CATEGORY_PROFESSIONAL, Exam::CATEGORY_CERTIFICATION, Exam::CATEGORY_PRACTICE],
            Exam::OWNER_CBT_CENTER,
            Exam::OWNER_ORGANIZATION => [
                Exam::CATEGORY_RECRUITMENT,
                Exam::CATEGORY_ASSESSMENT,
                Exam::CATEGORY_CERTIFICATION,
                Exam::CATEGORY_PROFESSIONAL,
                Exam::CATEGORY_PRACTICE,
                Exam::CATEGORY_GENERAL,
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function allowedModes(string $ownerType): array
    {
        return match ($ownerType) {
            Exam::OWNER_SECONDARY_SCHOOL => [Exam::MODE_TRADITIONAL],
            Exam::OWNER_PROFESSIONAL_SCHOOL,
            Exam::OWNER_CBT_CENTER,
            Exam::OWNER_ORGANIZATION => [Exam::MODE_TRADITIONAL, Exam::MODE_ADAPTIVE],
            default => [],
        };
    }

    public static function isValid(string $ownerType, string $category, string $mode): bool
    {
        return in_array($category, self::allowedCategories($ownerType), true)
            && in_array($mode, self::allowedModes($ownerType), true);
    }
}
