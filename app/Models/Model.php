<?php

namespace App\Models;

use mysqli;

class Model{
    protected $db_host = DB_HOST;
    protected $db_user = DB_USER;
    protected $db_pass = DB_PASS;
    protected $db_name = DB_NAME;

    protected $connection;

    protected $query;

    protected $sql, $data = [], $params;

    protected $orderBy;

    protected $table;

    public function __construct()
    {
        $this->connection();
    }

    public function connection()
    {
        $this->connection = new mysqli($this->db_host, $this->db_user, $this->db_pass, $this->db_name);

        if ($this->connection->connect_errno) {
            die('Error de conexión: ' . $this->connection->connect_error);
        }

    }

    public function query($sql, $data = [], $params = null){

        if ($data) {

            if ($params == null) {
                $params = str_repeat('s', count($data));
            }

            $smtp = $this->connection->prepare($sql);
            $smtp->bind_param($params, ...$data);
            $smtp->execute();

            $this->query = $smtp->get_result();

        }else{

            $this->query = $this->connection->query($sql);

        }

        return $this;
    }

    public function orderBy($column, $order = 'ASC'){
        $this->orderBy = " ORDER BY {$column} {$order}";

        return $this;
    }

    public function first(){

        if (empty($this->query)) {
            $this->query($this->sql, $this->data, $this->params);
        }

        return $this->query->fetch_assoc();
    }

    public function get(){
        if (empty($this->query)) {
            $this->query($this->sql, $this->data, $this->params);
        }

        return $this->query->fetch_all(MYSQLI_ASSOC);
    }

    public function paginate($cant = 15)
    {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;


        if ($this->sql) {
            
            $sql = $this->sql . " LIMIT " . ($page - 1) * $cant . ", $cant";
            $data = $this->query($sql, $this->data, $this->params)->get();

        }else{
            $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->table} LIMIT " . ($page - 1) * $cant . ", $cant";
            $data = $this->query($sql)->get();
        }



        $total = $this->query("SELECT FOUND_ROWS() as total")->first()['total'];
        

        //Recuperar uri
        $uri = $_SERVER['REQUEST_URI'];
        $uri = trim($uri, '/');

        //Eliminar el parámetro page
        if (strpos($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        $last_page = ceil($total / $cant);

        return [
            'total' => $total,
            'from' => ($page - 1) * $cant + 1,
            'to' => ($page - 1) * $cant + count($data),
            'current_page' => $page,
            'last_page' => $last_page,
            'next_page' => $page < $last_page ? '?page=' . ($page + 1) : null,
            'prev_page' => $page > 1 ? '?page=' . ($page - 1) : null,
            'data' => $data,
        ];

    }


    //Consultas
    public function all(){
        $sql = "SELECT * FROM {$this->table}";

        return $this->query($sql)->get();
    }

    public function find($id){
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";

        return $this->query($sql, [$id])->first();
    }

    public function where($column, $operator, $value = null){

        if ($value == null) {
            $value = $operator;
            $operator = '=';
        }

        if ($this->sql) {
            $this->sql .= " AND {$column} {$operator} ?";
        }else{
            $this->sql = "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->table} WHERE {$column} {$operator} ?";
        }
        $this->data[] = $value;

        /* $this->query($sql, [$value]); */

        return $this;

    }

    public function create($data){
        //INSERT INTO contacts (name, email, phone) VALUES (?, ?, ?)

        $columns = implode(', ', array_keys($data));
        $values = array_values($data);

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES (" . str_repeat('?,', count($values) - 1) . "?)";

        $this->query($sql, $values);

        $insert_id = $this->connection->insert_id;

        return $this->find($insert_id);
    }

    public function update($id, $data){

        //UPDATE contacts SET name = ?, email = ?, phone = ? WHERE id = 1

        $fields = [];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
        }

        $fields = implode(', ', $fields);

        $sql = "UPDATE {$this->table} SET {$fields} WHERE id = ?";

        $values = array_values($data);
        $values[] = $id;

        $this->query($sql, array_values($values));

        return $this->find($id);
    }

    public function delete($id){
        //DELETE FROM contacts WHERE id = 1

        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $this->query($sql, [$id]);

        return true;
    }
}