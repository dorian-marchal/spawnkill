<?php
namespace SpawnKill;

/**
 * Wrapper de CurlManager permettant de faire facilement des requêtes
 * parallèles vers l'API de JVC (avec login/pass déjà paramétrés).
 * Cette classe fille facilite la récupération des infos des topics.
 */
class TopicCurlManager extends SpawnKillCurlManager {

    /**
     * Topics dont on veut récupérer les infos
     */
    private $topics;

    public function __construct() {

        parent::__construct();

        $topics = array();

    }

    /**
     * Ajoute un topic à la liste des topics dont on veut les infos.
     */
    public function addTopic(Topic $topic) {
        $this->topics[] = $topic;
    }

    /**
     * Supprime tous les topics
     */
    public function clearTopics() {
        $this->topics = array();
    }

    /**
     * Récupère les données de la dernière page connue des topics.
     * @return array Données du type stdClass {
     *      "topic" : Topic
     *      "data" : stdClass {
     *          error: (boolean) : true si une erreur a été rencontrée (statut HTTP >= 400)
     *          pageCount: (int)
     *          postCount: (int)
     *          upToDate: (boolean) : true si la page récupérée est la dernière fu topic
     *          locked: (boolean)
     *       }
     * }
     */
    public function getTopicsData() {

        $topicsData = array();

        //On reset le tabloeau d'urls
        $this->urls = array();

        //On peuple $this->urls avec les urls des dernières pages des topics
        foreach ($this->topics as $topic) {
            $lastPageData = new \stdClass();
            $lastPageData->topic = $topic;
            $topicsData[] = $lastPageData;

            $this->urls[] = $topic->getPageUrl($topic->getPageCount());
        }

        //On récupère les infos des urls
        $requestsData = $this->processRequests();

        //On lie ces données aux topics correspondants
        for($i = 0; $i < count($requestsData); $i++) {
            $topicsData[$i]->data = $this->parseTopicData($requestsData[$i]);
        }

        return $topicsData;

    }

    /**
     * Extrait les infos d'un topic en fonction du html d'une page du topic
     * @return stdClass {
     *      error: (boolean) : true si une erreur a été rencontrée (statut HTTP >= 400)
     *      pageCount: (int)
     *      upToDate: (boolean) : true si la page récupérée est la dernière fu topic
     *      postCount: (int) : Seulement présent si upToDate = true
     *      locked: (boolean)
     * }
     */
    private function parseTopicData($requestResult) {

        $topicData = new \stdClass();

        //Erreur
        $topicData->error = $requestResult->httpCode >= 400;

        //Nombre de pages
        preg_match('/<count_page>(\\d*)<\\/count_page>/', $requestResult->data, $matches);
        $topicData->pageCount = intval($matches[1]);

        //À jour si la page récupérée est la dernière
        preg_match('/<num_page>(\\d*)<\\/num_page>/', $requestResult->data, $matches);
        $currentPageNumber = intval($matches[1]);
        $topicData->upToDate = $currentPageNumber === $topicData->pageCount;

        //Locké si le lien de réponse n'est pas présent
        preg_match('/<repondre>/', $requestResult->data, $matches);
        $topicData->locked = empty($matches);

        //Nombre de posts
        if($topicData->upToDate) {
            preg_match_all('/<b class=\\"cdv\\">/', $requestResult->data, $matches);
            $currentPagePostCount = intval(count($matches[0]));
            $topicData->postCount = (($topicData->pageCount - 1) * 20) + $currentPagePostCount;
        }

        return $topicData;
    } 


}