<?php
use Weaviate\Weaviate;

class StediAIVectorStore {
    private $client;
    private $className = 'ChatMessage';

    public function __construct() {
        $this->client = new Weaviate([
            'scheme' => 'https',
            'host' => 'uo7kugmq5a1c9ffsdgkcw.c0.us-east1.gcp.weaviate.cloud',
            'headers' => [
                'Authorization' => 'Bearer kYbRT8Q44cLb7X561aNBnSXt3fRnk983B3p9'
            ]
        ]);

        $this->createSchemaIfNotExists();
    }

    private function createSchemaIfNotExists() {
        if (!$this->client->schema()->exists($this->className)) {
            $this->client->schema()->create([
                'class' => $this->className,
                'properties' => [
                    [
                        'name' => 'content',
                        'dataType' => ['text'],
                    ],
                    [
                        'name' => 'timestamp',
                        'dataType' => ['date'],
                    ]
                ],
                'vectorizer' => 'text2vec-contextionary'
            ]);
        }
    }

    public function storeMessage($message) {
        $this->client->data()->create([
            'class' => $this->className,
            'properties' => [
                'content' => $message,
                'timestamp' => date('c')
            ]
        ]);
    }

    public function getRelevantContext($query, $limit = 5) {
        $result = $this->client->graphql()->get($this->className)
            ->withNearText(['concepts' => [$query]])
            ->withLimit($limit)
            ->withFields('content')
            ->do();

        $relevantDocuments = [];
        foreach ($result['data']['Get'][$this->className] as $item) {
            $relevantDocuments[] = $item['content'];
        }

        return implode("\n", $relevantDocuments);
    }
}