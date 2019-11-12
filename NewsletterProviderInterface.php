<?php

namespace BisgMarketing\Components\Newsletter;

/**
 *
 * @author Andreas Lang <andreas.lang@berliner-kaffeeroesterei.de>
 */
interface NewsletterProviderInterface
{
    const BISG_NL_PERSONAL = 'individuelle news';
    const BISG_NL_GENERAL = 'allgemeine newsletter';
    
    /**
     * take user input and set preferences to newsletter provider
     * @param array $xUser
     * @param array $xForm
     */
    public function setPreferences($xUser,$xForm): bool ;
    
    /**
     * fetches preferences to given user from email provider
     * @param array $xUser
     */
    public function getPreferences($xUser);
     
    /**
     * 
     * @param string $feld
     * @param boolean $status
     */
    public function setSwStatus($feld, $status):bool;
    
    /**
     * 
     * @param string $email
     * @param string $tag
     */
    public function addTag($email, $tag);
    
    /**
     * 
     * @param string $email
     * @param string $tag
     */
    public function removeTag($email, $tag);

}
