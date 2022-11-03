<?php

class ModelModuleAlcrfma extends Model {	
	
    public function setCustomerGroup($order = array()){
       if($order){
            $check = $this->db->query("SELECT * FROM " . DB_PREFIX . "alcrfm_customer_log WHERE order_id_last = '".$order['order_id']."'");
       if(!$check->num_rows){
        
            $this->load->model('setting/setting');  
            $settings = $this->model_setting_setting->getSetting('alcrfma');                          
            // FILTER AVG TOTAL
            $infodata_filter = array(
                'opt_group_id' => $settings['alcrfma_optgroup_id'],
                'min_total' => $settings['alcrfma_mintotal'],
                'max_total' => $settings['alcrfma_maxtotal'],
            );
            
            $all_order_totals = $this->getAvgAllOrdersTotal($infodata_filter);
            
            $avg_total_now = array(
                'max' => round((((int)$all_order_totals['min'] + (int)$all_order_totals['max']) / 2)*floatval($settings['alcrfma_ratio_summ_max'])),
                'min' => round((((int)$all_order_totals['min'] + (int)$all_order_totals['max']) / 2)*floatval($settings['alcrfma_ratio_summ_min'])),  
            );  
            
            $all_order_counts = $this->getAvgAllOrdersProductCount2($infodata_filter);

            $avg_count_now = array(
                'max' => round((((int)$all_order_counts['min'] + (int)$all_order_counts['max']) / 2)*floatval($settings['alcrfma_ratio_count_max'])),
                'min' => round((((int)$all_order_counts['min'] + (int)$all_order_counts['max']) / 2)*floatval($settings['alcrfma_ratio_count_min'])),  
            );             
            // Find CUSTOMER       
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "alcrfm_customer WHERE customer_id = '". (int)$order['customer_id'] ."'");
            
            if(!$query->num_rows){
                $tmp_customer_group = 301;
                $tmp_customer_zone = 3;
                      
                if((int)$order['total'] < $avg_total_now['min']){
                    $tmp_customer_group = $tmp_customer_group + 10;
                }
                if((int)$order['total'] > $avg_total_now['min'] && (int)$order['total'] < $avg_total_now['max']){
                    $tmp_customer_group = $tmp_customer_group + 20;
                }                        
                if((int)$order['total'] > $avg_total_now['max']){
                    $tmp_customer_group = $tmp_customer_group + 30;
                } 
                /////////// NEXT SAVE CUSTOMER
                $this->db->query("INSERT INTO `" . DB_PREFIX . "alcrfm_customer` (customer_id, rfm_zone_id, rfm_group_id, customer_name, customer_email, order_firstdate, order_lastdate, order_count, order_total, order_avg, product_count, product_avg) VALUES ('".(int)$order['customer_id']."','".$tmp_customer_zone."','".$tmp_customer_group."','".$order['firstname'].' '.$order['lastname']."','".$order['email']."','','".$order['date_added']."','1','".(int)$order['total']."','".(int)$order['total']."','','')");                          
                $this->db->query("INSERT INTO `" . DB_PREFIX . "alcrfm_customer_log`(customer_id, rfm_group_id_old, rfm_group_id_new, date, order_id_last, order_total) VALUES ('".(int)$order['customer_id']."','0','".$tmp_customer_group."','".$order['date_added']."','".$order['order_id']."','".(int)$order['total']."')");
            
            } else {
                $tmp_customer_group = 300;         
                $tmp_customer_zone = 3;
                $tmp_ord_count_customer = (int)$query->row['order_count'] + 1;
                $tmp_total_customer = round($query->row['order_total'] +  $order['total']);  
                $tmp_avg_total_customer = round($tmp_total_customer/$tmp_ord_count_customer);  
                  
                if($tmp_total_customer < $avg_total_now['min']){
                    $tmp_customer_group = $tmp_customer_group + 1;
                }
                if($tmp_total_customer > $avg_total_now['min'] && $tmp_total_customer < $avg_total_now['max']){
                    $tmp_customer_group = $tmp_customer_group + 2;
                }                        
                if($tmp_total_customer > $avg_total_now['max']){
                    $tmp_customer_group = $tmp_customer_group + 3;
                } 
                
                if($tmp_ord_count_customer < $avg_count_now['min']){
                    $tmp_customer_group = $tmp_customer_group + 10;
                }
                if($tmp_ord_count_customer > $avg_count_now['min'] && $tmp_ord_count_customer < $avg_count_now['max']){
                    $tmp_customer_group = $tmp_customer_group + 20;
                }                        
                if($tmp_ord_count_customer > $avg_count_now['max']){
                    $tmp_customer_group = $tmp_customer_group + 30;
                }          
                
                $this->db->query("UPDATE `" . DB_PREFIX . "alcrfm_customer` SET `rfm_zone_id`='".$tmp_customer_zone."',`rfm_group_id`='".$tmp_customer_group."', `order_lastdate`='".$order['date_added']."',`order_count`='".$tmp_ord_count_customer."',`order_total`='".$tmp_total_customer."',`order_avg`='".$tmp_avg_total_customer."',`product_count`='".$tmp_ord_count_customer."' WHERE customer_id ='".$order['customer_id']."'");
                $this->db->query("INSERT INTO `" . DB_PREFIX . "alcrfm_customer_log`(customer_id, rfm_group_id_old, rfm_group_id_new, date, order_id_last, order_total) VALUES ('".(int)$order['customer_id']."','".$query->row['rfm_group_id']."','".$tmp_customer_group."','".$order['date_added']."','".$order['order_id']."','".(int)$order['total']."')");                      
            }
       }
       } 
              
    }
    
    public function cronUpdate(){
        
    }

    public function getAvgAllOrdersTotal($data=array()){ 
        if(count($data)){
            $sql_min = "SELECT MIN(value) as min FROM " . DB_PREFIX . "order_total AS ot LEFT JOIN " . DB_PREFIX . "order AS o ON ot.order_id = o.order_id WHERE ot.code = 'total' AND o.customer_id > '0' ";
            $sql_avg = "SELECT AVG(value) as avg FROM " . DB_PREFIX . "order_total AS ot LEFT JOIN " . DB_PREFIX . "order AS o ON ot.order_id = o.order_id WHERE ot.code = 'total' AND o.customer_id > '0' ";
            $sql_max = "SELECT MAX(value) as max FROM " . DB_PREFIX . "order_total AS ot LEFT JOIN " . DB_PREFIX . "order AS o ON ot.order_id = o.order_id WHERE ot.code = 'total' AND o.customer_id > '0' ";
            
            if(isset($data['opt_group_id'])){
                $sql_min .= " AND o.customer_group_id != '". (int)$data['opt_group_id'] ."' ";
                $sql_avg .= " AND o.customer_group_id != '". (int)$data['opt_group_id'] ."' ";
                $sql_max .= " AND o.customer_group_id != '". (int)$data['opt_group_id'] ."' ";                
            }
            if(isset($data['min_total']) && $data['min_total']){
                $sql_min .= " AND ot.value > '". (int)$data['min_total'] ."' ";
                $sql_avg .= " AND ot.value > '". (int)$data['min_total'] ."' ";
                $sql_max .= " AND ot.value > '". (int)$data['min_total'] ."' ";                
            }
            if(isset($data['max_total']) && $data['max_total']){
                $sql_min .= " AND ot.value < '". (int)$data['max_total'] ."' ";
                $sql_avg .= " AND ot.value < '". (int)$data['max_total'] ."' ";
                $sql_max .= " AND ot.value < '". (int)$data['max_total'] ."' ";                
            }
            
            $query_min = $this->db->query($sql_min);
            $query_avg = $this->db->query($sql_avg);
            $query_max = $this->db->query($sql_max);               
        } else {
            $query_min = $this->db->query("SELECT MIN(value) as min FROM " . DB_PREFIX . "order_total WHERE code = 'total' ");
            $query_avg = $this->db->query("SELECT AVG(value) as avg FROM " . DB_PREFIX . "order_total WHERE code = 'total' ");
            $query_max = $this->db->query("SELECT MAX(value) as max FROM " . DB_PREFIX . "order_total WHERE code = 'total' ");            
        }

        
        $data = array(
            'min' => $query_min->num_rows ? round($query_min->row['min']) : 0,
            'avg' => $query_min->num_rows ? round($query_avg->row['avg']) : 0,
            'max' => $query_min->num_rows ? round($query_max->row['max']) : 0,
        );  
              
        $query_min = array();
        $query_avg = array();
        $query_max = array();      
        return $data;    
    }  


    public function getAvgAllOrdersProductCount2($data=array()){

        if(count($data)){
            $sql_min = "SELECT o.order_id, COUNT(*) as count FROM " . DB_PREFIX . "order AS o LEFT JOIN " . DB_PREFIX . "customer AS c ON o.customer_id = c.customer_id WHERE  o.customer_id > '0' ";            
            $sql_max = "SELECT o.order_id, COUNT(*) as count FROM " . DB_PREFIX . "order AS o LEFT JOIN " . DB_PREFIX . "customer AS c ON o.customer_id = c.customer_id WHERE  o.customer_id > '0' ";            

            if(isset($data['opt_group_id'])){
                $sql_min .= " AND c.customer_group_id != '". (int)$data['opt_group_id'] ."' ";
                $sql_max .= " AND c.customer_group_id != '". (int)$data['opt_group_id'] ."' ";
              
            }
            if(isset($data['min_total']) && $data['min_total']){
                $sql_min .= " AND o.total > '". (int)$data['min_total'] ."' ";
                $sql_max .= " AND o.total > '". (int)$data['min_total'] ."' ";             
            }
            if(isset($data['max_total']) && $data['max_total']){
                $sql_min .= " AND o.total < '". (int)$data['max_total'] ."' ";
                $sql_max .= " AND o.total < '". (int)$data['max_total'] ."' ";        
            }
                $sql_max .= " GROUP BY o.customer_id ORDER BY count DESC LIMIT 1";
                $sql_min .= " GROUP BY o.customer_id ORDER BY count ASC LIMIT 1";
 
            $query_max = $this->db->query($sql_max);
            $query_min = $this->db->query($sql_min);
                                            
        } else {
            $query_max = $this->db->query("SELECT order_id, COUNT(*) as count FROM `" . DB_PREFIX . "order_product` GROUP BY order_id ORDER BY count DESC LIMIT 1");
            $query_min = $this->db->query("SELECT order_id, COUNT(*) as count FROM `" . DB_PREFIX . "order_product` GROUP BY order_id ORDER BY count ASC LIMIT 1");          
        }
        

        if($query_min->num_rows && $query_max->num_rows){
            $avg = ((int)$query_min->row['count'] + (int)$query_max->row['count'])/2;
        }
        

        $data = array(
            'min' => $query_min->num_rows ? round($query_min->row['count']) : 0,
            'avg' => $avg > 0 ? round($avg) : 0,
            'max' => $query_min->num_rows ? round($query_max->row['count']) : 0,
        );

        $query_max = array();
        $query_min = array();
                       
        return $data;    
    }  
    
        
}

?>