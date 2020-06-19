<?php

namespace src;

use PDO;

/**
 * Class ProductService
 * @package src
 */
class ProductService{

    /**
     * @var Database
     */
    private $database;

    /**
     * @var \PDO
     */
    private $dbh;

    /**
     * @var
     */
    private $dbPrefix = '';

    public function __construct()
    {
        $this->database = new Database();
        $this->dbh = Database::getConnection();

        /*$config = Config::load();
        $dbConfig = $config['db'];
        $this->dbPrefix = $dbConfig['prefix'];*/
    }

    /**
     * @param int $limit
     * @param int $offset
     * @param null $threads
     * @param null $seed
     * @return array
     */
    public function getData(int $limit = 100, int $offset = 0, $threads = null, $seed = null)
    {
        $sql = 'SELECT 
                    catalog_product_entity.*,
                    categories_aggregated.category_id,
                    categories_aggregated.category_name,
                    res.rating_summary,
                    res.reviews_count,
                    ciss.qty as stock_quantity
                FROM ' . $this->dbPrefix . 'catalog_product_entity catalog_product_entity
                LEFT JOIN (
                    SELECT 
                        catalog_category_product.product_id, 
                        catalog_category_product.category_id, 
                        catalog_category_entity_varchar.value as category_name
                    
                    FROM ' . $this->dbPrefix . 'catalog_category_product catalog_category_product
                    INNER JOIN ' . $this->dbPrefix . 'catalog_category_entity cce
                    ON catalog_category_product.category_id = cce.entity_id
                    INNER JOIN ' . $this->dbPrefix . 'catalog_category_entity_varchar catalog_category_entity_varchar
                    ON catalog_category_entity_varchar.entity_id = catalog_category_product.category_id
                    INNER JOIN ' . $this->dbPrefix . 'eav_attribute ea
                    ON ea.attribute_id = catalog_category_entity_varchar.attribute_id
                    WHERE  ea.entity_type_id=3 AND store_id = 0 AND attribute_code = "name"
                ) categories_aggregated
                ON catalog_product_entity.entity_id = categories_aggregated.product_id
                
                LEFT JOIN
                (SELECT * FROM ' . $this->dbPrefix . 'review_entity_summary WHERE entity_type = 1 AND store_id = 0 ) res
                ON catalog_product_entity.entity_id = res.entity_pk_value
                
                LEFT JOIN
                (SELECT * FROM ' . $this->dbPrefix . 'cataloginventory_stock_status WHERE stock_id = 1 AND website_id = 0 ) ciss
                ON catalog_product_entity.entity_id = ciss.product_id
                
                WHERE 1 = 1 #AND entity_id = 43871 
                
                ';

        if($threads && $seed !== null){
            $sql .= ' AND entity_id % ' . $threads . ' = ' . $seed;
        }

        $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->dbh->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $stmt->execute([
            ':limit' => $limit,
            ':offset' => $offset,
        ]);

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    /**
     * @param array $row
     * @return array
     */
    public function getRow(array $row){
        $id = $row['entity_id'];
        $data = [
            'id' => $row['entity_id'],
            'sku' => $row['sku'],
            'attribute_set_id' => $row['attribute_set_id'],
            'type_id' => $row['type_id'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'required_options' => $row['required_options'],
            'categories' => [$row['category_name']],
            'category_ids' => [$row['category_id']],
            'rating_summary' => $row['rating_summary'],
            'reviews_count' => $row['reviews_count'],
            'stock_quantity' => intval($row['stock_quantity'])
        ];

        $characteristics = [];

        $eavAttributes = $this->getEavAttributes($id);


        foreach ($eavAttributes as $attribute){
            $code = $attribute['attribute_code'];
            $value = $attribute['value'];
            $data[$code] = $value;

            if($attribute['is_user_defined']){
                $characteristics[] = [
                    'label' => $attribute['frontend_label'],
                    'value' => $attribute['value']
                ];
            }
        }

        $data['characteristics'] = $characteristics;
        $data['name_exact'] = $data['name'];
        $data['name_suggest'] = $this->getNameSuggest($data['name']);

        return $data;
    }

    /**
     * @param $entityId
     * @return array
     */
    private function getEavAttributes($entityId){
        $sql = '
            (SELECT
                  entity_id,
                attribute_code,
                frontend_label,
                value,
                is_user_defined,
                store_id
            FROM ' . $this->dbPrefix . 'catalog_product_entity_varchar catalog_product_entity
            INNER JOIN ' . $this->dbPrefix . 'eav_attribute ea
            ON ea.attribute_id = catalog_product_entity.attribute_id
            WHERE catalog_product_entity.entity_id = :entity_id AND ea.entity_type_id=4 AND store_id = 0
            )
            
            UNION
            
            (
            SELECT
                  entity_id,
                attribute_code,
                frontend_label,
                value,
                is_user_defined,
                store_id
            FROM ' . $this->dbPrefix . 'catalog_product_entity_text catalog_product_entity
            INNER JOIN ' . $this->dbPrefix . 'eav_attribute ea
            ON ea.attribute_id = catalog_product_entity.attribute_id
            WHERE catalog_product_entity.entity_id = :entity_id AND ea.entity_type_id=4 AND store_id = 0
            )
            
            UNION
            
            (
            SELECT
                  entity_id,
                attribute_code,
                frontend_label,
                value,
                is_user_defined,
                store_id
            FROM ' . $this->dbPrefix . 'catalog_product_entity_int catalog_product_entity
            INNER JOIN ' . $this->dbPrefix . 'eav_attribute ea
            ON ea.attribute_id = catalog_product_entity.attribute_id
            WHERE catalog_product_entity.entity_id = :entity_id AND ea.entity_type_id=4 AND store_id = 0
            )
            
            UNION
            
            (
            SELECT
                  entity_id,
                attribute_code,
                frontend_label,
                value,
                is_user_defined,
                store_id
            FROM ' . $this->dbPrefix . 'catalog_product_entity_decimal catalog_product_entity
            INNER JOIN ' . $this->dbPrefix . 'eav_attribute ea
            ON ea.attribute_id = catalog_product_entity.attribute_id
            WHERE catalog_product_entity.entity_id = :entity_id AND ea.entity_type_id=4 AND store_id = 0
            )';

        $stmt = $this->dbh->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
        $stmt->execute([':entity_id' => $entityId,]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    /**
     * @param $name
     * @return array
     */
    private function getNameSuggest($name){
        $words = explode(' ',$name);

        $input = [];
        foreach ($words as $word){
            if(strlen($word) > 3){
                $input[] = $word;
            }
        }

        return [
            "input" => $input
        ];
    }
}
