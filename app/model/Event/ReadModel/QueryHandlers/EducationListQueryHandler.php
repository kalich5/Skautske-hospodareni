<?php

declare(strict_types=1);

namespace Model\Event\ReadModel\QueryHandlers;

use Model\Event\Education;
use Model\Event\ReadModel\Queries\EducationListQuery;
use Model\Skautis\Factory\EducationFactory;
use Skautis\Skautis;
use function is_object;

class EducationListQueryHandler
{
    /** @var Skautis */
    private $skautis;

    /** @var EducationFactory */
    private $educationFactory;

    public function __construct(Skautis $skautis, EducationFactory $educationFactory)
    {
        $this->skautis          = $skautis;
        $this->educationFactory = $educationFactory;
    }

    /**
     * @return array<int, Education> Educations indexed by ID
     */
    public function __invoke(EducationListQuery $query) : array
    {
        $educations = $this->skautis->event->EventEducationAll([
            'Year' => $query->getYear(),
        ]);

        if (is_object($educations)) {
            return [];
        }

        $result = [];
        foreach ($educations as $education) {
            $education = $this->educationFactory->create($education);

            $result[$education->getId()->toInt()] = $education;
        }

        return $result;
    }
}