<?php

namespace AppBundle\Repository;

use AppBundle\Entity\FennecUser;
use AppBundle\Entity\WebuserData;
use Doctrine\ORM\EntityRepository;

/**
 * WebuserDataRepository
 *
 * This class was generated by the PhpStorm "Php Annotations" Plugin. Add your own custom
 * repository methods below.
 */
class WebuserDataRepository extends EntityRepository
{
    public function getNumberOfProjects(FennecUser $user = null): int{
        if ($user === null) {
            return 0;
        }
        return count($this->findBy(['webuser' => $user]));
    }

    public function getDataForUser($userId) {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('IDENTITY(data.webuser) AS webuserId', 'p.permission', 'data.webuserDataId', 'data.importDate', 'data.importFilename', 'data.project')
            ->from('AppBundle\Entity\Permissions', 'p')
            ->innerJoin('AppBundle\Entity\WebuserData', 'data', 'WITH', 'p.webuserData = data.webuserDataId')
            ->where('p.webuser = :userId')
            ->setParameter('userId', $userId);
        $query = $qb->getQuery();
        return $query->getResult();
    }

    public function getDataByProjectId($projectId) {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('IDENTITY(data.webuser) AS webuserId', 'data.webuserDataId', 'data.importDate', 'data.importFilename', 'data.project')
            ->from('AppBundle\Entity\WebuserData', 'data')
            ->where('data.webuserDataId = :projectId')
            ->setParameter('projectId', $projectId);
        $query = $qb->getQuery();
        return $query->getResult();
    }
}
