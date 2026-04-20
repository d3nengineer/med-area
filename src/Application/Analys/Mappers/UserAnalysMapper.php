<?php

declare(strict_types=1);

namespace Application\Analys\Mappers;

use Domain\Analys\DTO\UserAnalysDTO;
use Domain\Analys\Enums\Analys;

class UserAnalysMapper
{
    public function assignAnalysNameFromEnum(UserAnalysDTO $userAnalys): UserAnalysDTO
    {
        if ($userAnalys->isNotEmptyValue('analys_id') && $userAnalys->emptyValue('analys_name')) {
            /** @var Analys $analysId */
            $analysId = $userAnalys->analys_id;
            $userAnalys->analys_name = $analysId->name;
        }

        return $userAnalys;
    }
}
