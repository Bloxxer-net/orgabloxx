<?php

class LLMClient
{
    private string $endpoint;
    private string $apiKey;
    private string $apiVersion;
    private string $deploymentSummarize;
    private string $deploymentExpand;
    private string $deploymentRevision;

    public function __construct(array $config)
    {
        $azure = $config['azure_openai'];
        $this->endpoint = rtrim($azure['endpoint'], '/');
        $this->apiKey = $azure['api_key'];
        $this->apiVersion = $azure['api_version'];
        $this->deploymentSummarize = $azure['deployment_summarize'];
        $this->deploymentExpand = $azure['deployment_expand'];
        $this->deploymentRevision = $azure['deployment_revision'];
    }

    private function makeRequest(string $deployment, array $messages): ?string
    {
        $isConfigured = !empty($this->apiKey)
            && !empty($this->endpoint)
            && strpos($this->apiKey, 'your-') === false
            && strpos($this->endpoint, 'your-resource-name') === false;

        if (!$isConfigured) {
            return null;
        }

        $url = sprintf('%s/openai/deployments/%s/chat/completions?api-version=%s',
            $this->endpoint,
            rawurlencode($deployment),
            $this->apiVersion
        );

        $payload = [
            'messages' => $messages,
            'temperature' => 0.4,
            'max_tokens' => 800,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'api-key: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            error_log('Azure OpenAI request failed: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400) {
            error_log('Azure OpenAI error: ' . $response);
            return null;
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    public function summarize(string $text): ?string
    {
        $prompt = "Erstelle eine prägnante, stichwortartige Zusammenfassung des folgenden Textes. Nutze maximal 5 Bullet-Points. Text:\n" . $text;
        return $this->makeRequest($this->deploymentSummarize, [
            ['role' => 'system', 'content' => 'Du bist ein hilfreicher Assistent, der Texte in Stichpunkte zusammenfasst.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    public function expandFromBullets(string $bullets): ?string
    {
        $prompt = "Formuliere den folgenden Stichpunkttext zu einem flüssigen Absatz aus. Nutze sachliche Sprache. Stichpunkte:\n" . $bullets;
        return $this->makeRequest($this->deploymentExpand, [
            ['role' => 'system', 'content' => 'Du bist ein technischer Redakteur, der Stichpunkte in ausformulierte Absätze überführt.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    public function suggestRevision(string $existingText, string $bullets): ?string
    {
        $prompt = "Der folgende Absatz soll anhand neuer Stichpunkte überarbeitet werden. Schlage konkrete Textänderungen vor. Bestehender Text:\n{$existingText}\n\nStichpunkte:\n{$bullets}\n\nErzeuge einen Vorschlag, der die Änderungen beschreibt und optional neue Formulierungen enthält.";
        return $this->makeRequest($this->deploymentRevision, [
            ['role' => 'system', 'content' => 'Du bist ein Lektor, der Änderungs- und Ergänzungsvorschläge präzise beschreibt.'],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }
}
