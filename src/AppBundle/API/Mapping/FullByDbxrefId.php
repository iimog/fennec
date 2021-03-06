<?php

namespace AppBundle\API\Mapping;

use AppBundle\API\Webservice;
use AppBundle\User\FennecUser;
use Symfony\Component\HttpFoundation\ParameterBag;

class FullByDbxrefId extends Webservice
{
    private $db;

    /**
     * @inheritdoc
     */
    public function execute(ParameterBag $query, FennecUser $user = null)
    {
        $this->db = $this->getManagerFromQuery($query)->getConnection();
        if(!$query->has('db')){
            return array();
        }
        $query_get_mapping = <<<EOF
SELECT fennec_id, identifier AS ncbi_taxid
    FROM fennec_dbxref
        WHERE db_id=(SELECT db_id FROM db WHERE name=?)
EOF;
        $stm_get_mapping = $this->db->prepare($query_get_mapping);
        $stm_get_mapping->execute([$query->get('db')]);

        $result = array();
        while($row = $stm_get_mapping->fetch(\PDO::FETCH_ASSOC)){
            $ncbiID = $row['ncbi_taxid'];
            if(!array_key_exists($ncbiID, $result)){
                $result[$ncbiID] = $row['fennec_id'];
            } else {
                if(! is_array($result[$ncbiID]) ){
                    $result[$ncbiID] = [$result[$ncbiID]];
                }
                $result[$ncbiID][] = $row['fennec_id'];
            }
        }

        return $result;
    }
}