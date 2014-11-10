<?php

/**
 * A database class to simplify accessing wpdb
 *
 * @author tobias
 *
 */
class EmailSubscriptionDatabase{


    private $address_table;
    private $spool_table;

    public function __construct(){
        global $wpdb;

        $this->address_table = $wpdb->prefix . "emailSub_addresses";
        $this->spool_table = $wpdb->prefix . "emailSub_spool";
    }

    /**
     * Returns true if email was added. It does also returns true if email already is in the database
     *
     * @param string $email
     */
    public function addEmail($email, $language = ""){
        global $wpdb;

        $email=trim($email);

        //check if exists
        $res=$wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->address_table} WHERE email='%s' AND language ='%s'",
                array($email, $language)
            ));

        if($res) {
            //Already exists
            return true;
        }

        //insert
        $wpdb->query(
            $wpdb->prepare("INSERT INTO {$this->address_table} SET email='%s', language='%s' ",
                array($email, $language)
            ));

        return true;
    }

    /**
     * This fancy long named function does check for email addresses to send subscription email to.
     *
     * This function does also remove the email addresses from the email spool.
     *
     * Max $limit emails are returned. $count is the total number of emails that haven't been mailed,
     * excluding the ones returned now.
     *
     * @param int &$count
     * @param int $limit
     */
    public function getEmailsToSendSubscriptionMails(&$count, $limit=2){
        global $wpdb;

        $results=$wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.email, a.language, s.post_id, s.spool_id FROM {$this->spool_table} s, {$this->address_table} a WHERE a.email_id=s.email_id ORDER BY spool_id LIMIT %d",
                array($limit)),
            ARRAY_A);

        if(!$results){
            $count=0;
            return array();
        }

        $spoolIds=array();
        foreach($results as $idx=>$row){
            //save id
            $spoolIds[]=$row['spool_id'];

            //remove id from results
            unset($results[$idx]['spool_id']);
        }


        //remove these emails from spool
        $wpdb->query("DELETE FROM {$this->spool_table} WHERE spool_id IN (".implode(',',$spoolIds).")");

        $count = $this->getSpoolCount();

        return $results;
    }

    public function getSpoolCount() {
        global $wpdb;

        return $wpdb->get_var("SELECT count(email_id) FROM {$this->spool_table} ");
    }

    /**
     * Returns all emails
     */
    public function getAllEmails(){
        global $wpdb;

        return $wpdb->get_results("SELECT * FROM {$this->address_table} ORDER BY language, email_id");
    }

    /**
     * Copy all addresses from db to the email spool
     *
     * @param unknown_type $postId
     */
    public function addAllToSpool($postId, $language = ""){
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$this->spool_table} (email_id, post_id)
					SELECT a.email_id, '%d' FROM {$this->address_table} a WHERE language='%s'"
                ,array($postId, $language))
        );
    }

    /**
     * Add one email to spool.. Like an administrator when testing..
     *
     * @param $email
     * @param $postId
     * @param string $language
     */
    public function addToSpool($email, $postId, $language = ""){
        global $wpdb;

        $this->addEmail($email);
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$this->spool_table} (email_id, post_id)
					SELECT a.email_id, '%d' FROM {$this->address_table} a WHERE email='%s' AND language='%s'"
                ,array($postId, $email, $language))
        );
    }

    /**
     * Removes an emails from spool and address table
     *
     * @param string $email
     */
    public function removeEmail($email){
        global $wpdb;

        //get id
        $email_id= $wpdb->get_var(
            $wpdb->prepare(
                "SELECT email_id FROM {$this->address_table} WHERE email='%s' "
                ,array($email)));

        //remove from address table
        $wpdb->query(
            $wpdb->prepare("DELETE FROM {$this->address_table} "
                ."WHERE email_id='%d' "
                ,array($email_id)));

        //remove from spool table
        $wpdb->query(
            $wpdb->prepare("DELETE FROM {$this->spool_table} "
                ."WHERE email_id='%d' "
                ,array($email_id)));

    }
}