<?php

namespace Webkul\UVDesk\AutomationBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Query;

/**
 * PreparedResponsesRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PreparedResponsesRepository extends EntityRepository
{
	public $safeFields = array('page','limit','sort','order','direction');
    const LIMIT = 10;

	public function getPreparesResponses(\Symfony\Component\HttpFoundation\ParameterBag $obj = null, $container) {
        
        $userService = $container->get('user.service');
       // $currentUser = $userService->getCurrentUser();
        $json = array();
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('DISTINCT pr.id, pr.name, pr.status, u.id as agentId')->from($this->getEntityName(), 'pr')
            ->leftJoin('pr.user', 'ud')
            ->leftJoin('ud.user', 'u');

        $data = $obj->all();
        $data = array_reverse($data);
        foreach ($data as $key => $value) {
            if(!in_array($key,$this->safeFields)) {
                if($key!='dateUpdated' AND $key!='dateAdded' AND $key!='search') {
                    $preparedResponsesColumns = $this->getEntityColumnValues();
                    if (in_array($key, $preparedResponsesColumns)) {
                        $qb->Andwhere('pr.'.$key.' = :'.$key);
                        $qb->setParameter($key, $value);
                    }
                } else {
                    if($key == 'search') {
                        $qb->andwhere('pr.name'.' LIKE :name');
                        $qb->setParameter('name', '%'.urldecode(trim($value)).'%');    
                    }
                }
            }
        }   
 
        if(!isset($data['sort']))
            $qb->orderBy('pr.id',Criteria::DESC);

        $paginator  = $container->get('knp_paginator');

        $newQb = clone $qb;
        $newQb->select('COUNT(DISTINCT pr.id)');

        $results = $paginator->paginate(
            $qb->getQuery()->setHydrationMode(Query::HYDRATE_ARRAY)->setHint('knp_paginator.count', $newQb->getQuery()->getSingleScalarResult()),
            isset($data['page']) ? $data['page'] : 1,
            self::LIMIT,
            array('distinct' => false)
        );

        $paginationData = $results->getPaginationData();
        $queryParameters = $results->getParams();

        $paginationData['url'] = '#'.$container->get('uvdesk.service')->buildPaginationQuery($queryParameters);


        $data = $results->getItems();
        foreach ($data as $key => $row) {
            $data[$key]['user'] = $userService->getAgentDetailById($row['agentId']);
        }

        $json['preparedResponses'] = $data;
        $json['pagination_data'] = $paginationData;
       
        return $json;
    }

    public function getPreparedResponse($id) 
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('DISTINCT pr')->from($this->getEntityName(), 'pr')
            ->leftJoin('pr.user', 'ud')
            ->leftJoin('ud.user', 'u')
            ->andWhere('pr.id'.' = :id')
            ->setParameter('id', $id)
            ->groupBy('pr.id');
            
        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getEntityColumnValues() {
        return $this->getEntityManager()->getClassMetadata(PreparedResponses::class)->getColumnNames();
    }
}
