<?php

/**
 * Base record type class, to represent the Sugar CRM records
 */
class Record {

    static $_cache = array();
    static $_pool = array();
    var $module;
    var $id;
    var $name_value_list = array();

    /**
     * 
     * @param type $module
     * @param type $data
     * @param type $id
     */
    function __construct($module, $data, $id = false) {
        $this->module = $module;
        $this->name_value_list = $data;
        $this->id = $id;
        $id ? self::$_cache[$id] = $this : self::$_pool[] = $this;
    }

    /**
     * 
     * @return boolean
     */
    function isDeleted() {
        if ($this->name_value_list->deleted->value == 1)
            return true;
        return false;
    }

    /**
     * 
     * @param type $key
     * @param type $value
     */
    function set($key, $value) {
        $this->name_value_list->$key->value = $value;
    }

    /**
     * 
     * @param type $key
     * @param type $return
     * @return type
     */
    function get($key, $return = null) {
        if (isset($this->name_value_list->$key->value))
            return $this->name_value_list->$key->value;
        return $return;
    }

    /**
     * 
     * @return type
     */
    function getId() {
        return $this->id;
    }

    /**
     * 
     * @return type
     */
    function getModuleName() {
        return $this->module;
    }

    /**
     * 
     * @return type
     */
    function getNameValueList() {
        return $this->name_value_list;
    }

    /**
     * 
     */
    function updateInCache() {
        self::$_cache[$this->id] = $this;
    }

    /**
     * 
     * @return type
     */
    function save() {
        $parameters = array(
            'session' => User::getSessionId(),
            'module' => $this->getModuleName(),
            'name_value_list' => $this->getNameValueList()
        );
        $response = REST::doCall('set_entry', $parameters);
        return $response;
    }

    /**
     * 
     */
    function update() {
        $response = $this->save();
        $this->name_value_list = $response->entry_list;
        $this->updateInCache();
    }

    /**
     * 
     * @return type
     */
    function delete() {
        if (!$this->isDeleted()) {
            $this->set("deleted", 1);
            $this->update();
        }
        return $this->isDeleted();
    }

    /**
     * 
     * @param type $response
     * @return boolean
     */
    static function hasError($response) {
        if (strtolower($response->entry_list[0]->name_value_list[0]->name) === "warning")
            return true;

        return false;
    }

    /**
     * 
     * @param type $id
     * @param type $module
     * @param type $select_fields
     * @param type $link_name_to_fields_array
     * @return \Record
     * @throws Exception
     */
    static function retrieve($id, $module, $select_fields = null, $link_name_to_fields_array = null) {
        if (self::$_cache[$id])
            return self::$_cache[$id];

        $parameters = array(
            'session' => User::getSessionId(),
            'module' => $module,
            'id' => $id,
            'link_name_to_fields_array' => $link_name_to_fields_array,
        );
        $response = REST::doCall('get_entry', $parameters);

        if (!Record::hasError($response)) {
            $entry = $response->entry_list[0];
            $record = new Record($entry->module_name, $entry->name_value_list, $entry->id);
            return $record;
        }

        throw new Exception("Error retrieving the record");
    }

    /**
     * 
     * @return type
     * @throws Exception
     */
    static function saveAll() {

        if (!empty(self::$_pool))
            $module = self::$_pool[0]->getModuleName();
        else
            throw new Exception("Data set is empty for batch operation");


        $entries = array();
        foreach (self::$_pool as $i => $obj) {
            $entries[] = $obj->getNameValueList();
        }

        $parameters = array(
            'session' => User::getSessionId(),
            'module' => $module,
            'name_value_lists' => $entries
        );

        $response = REST::doCall('set_entries', $parameters);
        return $response;
    }

    /**
     * 
     * @param type $module
     * @return type
     * @throws Exception
     */
    static function listAll($module) {
        if (empty($module))
            throw new Exception("Module name is not specified");

        $parameters = array(
            'session' => User::getSessionId(),
            'module' => $module,
        );

        $response = REST::doCall('get_entry_list', $parameters);
        $recordids = array();
        foreach ($response->entry_list as $i => $obj) {
            $record = new Record($module, $obj->name_value_list, $obj->id);
            $recordids[] = $obj->id;
        }
        return $recordids;
    }

    /**
     * 
     * @param type $parentId
     * @param type $parentModule
     * @param type $field
     * @param type $childIds
     * @return type
     */
    static function setRelation($parentId, $parentModule, $field, $childIds = array()) {
        $parameters = array(
            'session' => User::getSessionId(),
            'module_name' => $parentModule,
            'module_id' => $parentId,
            'link_field_name' => $field,
            'related_ids' => $childIds,
        );
        $response = REST::doCall('set_relationship', $parameters);
        return $response;
    }

}

?>
