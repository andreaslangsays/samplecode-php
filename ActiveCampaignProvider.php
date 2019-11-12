<?php

namespace BisgMarketing\Components\Newsletter;

use Bisg\Components\BisgComponentBase;
use ActiveCampaign as ActiveCampaignApi;
use BisgMarketing\Components\Newsletter\NewsletterProviderInterface;
use Doctrine\DBAL\Connection;

class ActiveCampaignProvider extends BisgComponentBase implements NewsletterProviderInterface
{

    protected $api_key;
    protected $api_url;
    protected $list_name;
    protected $options_field = '%BENACHRICHTIGUNGS_OPTIONEN%';
    protected $options_field_id = null;
    protected $xUser = null;
    protected $bStatus;

    /** @var ActiveCampaignApi */
    protected $ac;

    /** @var Connection */
    protected $dbalConnection;

    public function __construct(Connection $dbalConnection)
    {
        parent::__construct("BisgMarketing");
        $this->dbalConnection = $dbalConnection;
    }

    public function init()
    {
        $pluginConfig = $this->PluginConfiguration();
        if ($pluginConfig->getValue("newslettertool") == "activecampaign") {
            $this->api_key = $pluginConfig->getValue("api_key");
            $this->api_endpoint = $pluginConfig->getValue("api_url");
            $this->list_name = strtolower($pluginConfig->getValue("api_list_name"));
            $this->options_field = $pluginConfig->getValue("api_options_field");
            $this->ac = new ActiveCampaignApi($this->api_url, $this->api_key);
        }
        $this->bStatus = false;
    }

    public function testApi()
    {

        $this->ac->debug = true;
        $response = $this->ac->api("list/field/view?ids=all");
        if ((int) $response->success) {
            // successful request
            $items = array();
            foreach ($response as $key => $value) {
                if (is_int($key)) {
                    $items[] = $value;
                }
            }

            if (count($items) == 20) {
                // fetch next page
            }
        } else {
            // request error
            return $response->error;
        }
        return $items;
    }

    public function getList($name = "")
    {
        if ($name <> "") {
            $this->list_name = strtolower($name);
        }
        $response = $this->ac->api("list/list?ids=all");
        foreach ($response as $list) {
            if (strtolower($list->name) == $this->list_name) {
                return $list->id;
            }
        }
        return false;
    }

    public function getContact($email)
    {
        $mail = urlencode(strtolower($email));
        $response = $this->ac->api("contact/view?id=" . $mail);
        if ((int) $response->success) {
            return $response;
        } else {
            return $response->error;
        }
    }

    public function getFormHtml($id = "1")
    {
        $response = $this->ac->api("form/html?id=1");
        return $response;
    }

    public function getFields()
    {
        $ac = new ActiveCampaign($this->api_url, $this->api_key);
        $response = $this->ac->api("list/field/view?ids=all");
        return $response;
    }

    /**
     * @param array $contact
     */
    public function syncContact(array $contact)
    {
            $response = $this->ac->api("contact/sync", $contact);
            return $response;
    }

    public function getContactStatus($email = false)
    {
        if (!$email) {
            $sUserData = Shopware()->Modules()->Admin()->sGetUserData();
            $email = $sUserData['additional']['user']['email'];
        }
        $list = $this->getList();
        if ($email) {
            $contact = $this->getContact($email);
            if ($contact->lists->{$list}->status == 1) {
                //subscribed
                return true;
            } elseif ($contact->lists->{$list}->status == 0) {
                //unsubscribed
                return false;
            }
        }
        //no or unexpected result
        return false;
    }

    public function subscribe()
    {
     
        $contact = $this->getContactTemplate(1);
        $this->bStatus = 1;
        return $this->syncContact($contact);
    }

    public function unsubscribe()
    {
        $contact = $this->getContactTemplate("2");
        $this->bStatus = 2;
               
        return $this->syncContact($contact);
    }

    /**
     *
     * @param string $status 1=subscribed 2=unsubscribed
     * @return type
     */
    protected function getContactTemplate($status = 1)
    {
        if (is_null($this->xUser)) {
            $this->xUser = Shopware()->Modules()->Admin()->sGetUserData();
        }
        $list = $this->getList();
        $email = $this->xUser['additional']['user']['email'];
        $xContact = $this->getContact($email);
            $aContact = [
                "email" => $email,
                "first_name" => $this->xUser['billingaddress']['firstname'],
                "last_name" => $this->xUser['billingaddress']['lastname'],
                "orgname" => $this->xUser['billingaddress']['company'],
                "p[" . $list . "]" => $list, // list ID
                "field[%CUSTOMERID%,0]" => $this->xUser['additional']['user']['customernumber'],
                "field[%GROUPID%,0]" => $this->xUser['additional']['user']['bisg_kundengruppe'],
                "field[%FIRMA%,0]" => $this->xUser['billingaddress']['company'],
                "field[%ONLINESHOP%,0]" => $this->xUser['additional']['user']['active'] ? "yes" : "no",
                "field[%KUNDE_SEIT%,0]" => $this->xUser['additional']['user']['firstlogin'],
                "status[" . $list . "]" => $status,             ];
        return $aContact;
    }

    public function addTag($email, $tag)
    {
        $this->ac->api("contact/tag_add", ["email" => $email, "tags" => $tag]);
    }

    public function removeTag($email, $tag)
    {
        $this->ac->api("contact/tag_remove", ["email" => $email, "tags" => $tag]);
    }


    /**
     * Sets preferences based on form submit
     * @param array $xUser
     * @param \Enlight_Controller_Request_Request $xForm
     * @return bool
     */
    public function setPreferences($xUser, $xForm): bool
    {
        $this->xUser = $xUser;
        $email = $xUser['additional']['user']['email'];
        $newsletterGeneral = (is_null($xForm->getParam('newsletter-general'))) ? false : true;
        $contact = $this->getContact($email);
        /**
         * if both are false, unsub customer
         */
        if (!$newsletterGeneral && $contact->status == '1') {
            $this->unsubscribe();
        } elseif ($contact->status <> '1') {
            $xForm->setPost("newsletter", "on");
            $this->subscribe();
        }
        return true;
    }

    public function getPreferences($xUser)
    {
        $this->xUser = $xUser;
        $email = $this->xUser['additional']['user']['email'];
        $newsletter = $this->xUser['additional']['user']['newsletter'];
        $contact = $this->getContact($email);
        if($this->bStatus) {
            $contact->status = $this->bStatus;
        }
        if ($contact->status == '1') {
            //set sw-newsletter flag to "subscribed"
            $feld = "newsletter";
            $status = 1;
            $refreshContact = $this->getContactTemplate("1");
            $this->syncContact($refreshContact);
            if ($newsletter == $status) {
                return;
            } else {
                $this->dbalConnection->insert('s_campaigns_mailaddresses', ['email' => $email]);
            }
        } else if ($contact->status == '2') {
            //set sw-newsletter flag to "subscribed"
            $feld = "newsletter";
            $status = 0;
            if ($newsletter == $status) {
                return;
            } else {
                $this->dbalConnection->delete('s_campaigns_mailaddresses', ['email' => $email]);
            }
        } else {
            $xUser['additional']['user']['newsletter'] = 0;
            return false;
        }

        $data = [$feld => $status];
        $identifier = ["id" => $this->xUser['additional']['user']['id']];
        $this->dbalConnection->update("s_user", $data, $identifier);
    }

    /**
     * set Status to shopware fields
     *
     * @param string $feld
     * @param boolean $status
     */
    public function setSwStatus($feld, $status): bool
    {
        //constrain values for $field
        $allowedFields = ['personal', 'general'];
        $status = ($status) ? '1' : '0';
        if (in_array($feld, $allowedFields)) {
            $feld = "bisg_newsletter_" . $feld;
            $data = [$feld => $status];
            $identifier = ["userID" => $this->xUser['additional']['user']['id']];
            $this->dbalConnection->update("s_user_attributes", $data, $identifier);
            return true;
        } else {
            return false;
        }
    }

    /**
     *
     * @param array $aOptions
     * @return type
     */
    protected function setOptionsfields($aOptions)
    {
        $email = $this->xUser['additional']['user']['email'];
        $contact = $this->getContact($email);
        $setOptions = [];
        $fields = $contact->fields;
        foreach ($fields as $field) {
            if ($field->tag == "%BENACHRICHTIGUNGS_OPTIONEN%") {
                $options = $field->options;
                foreach ($options as $option) {
                    if (in_array(strtolower($option->name), $aOptions)) {
                        $setOptions[] = $option->value;
                    }
                }
            }
        }
        $aContact = $this->getContactTemplate(1);
        $aContact['field[' . $this->options_field_id . ',0]'] = implode('||', $setOptions);
        return $this->syncContact($aContact);
    }

}
