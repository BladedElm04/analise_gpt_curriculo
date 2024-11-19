<?php

require 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuração da API da OpenAI
$openaiApiKey = $_ENV["API_KEY"];

// Função para extrair texto de um arquivo PDF
function extrairTextoPdf($caminhoPdf)
{
    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($caminhoPdf);
        return $pdf->getText();
    } catch (Exception $e) {
        return null;
    }
}

// Função para enviar o texto para a OpenAI
function analisarCurriculoComOpenAI($textoCurriculo, $criterios, $openaiApiKey)
{
    $url = 'https://api.openai.com/v1/chat/completions';

    $prompt = "
    Avalie o seguinte currículo em relação aos critérios da vaga:

    Critérios da vaga:
    - Cargo: {$criterios['cargo']}
    - Competências: " . implode(", ", $criterios['competencias']) . "
    - Experiência: {$criterios['experiencia']}
    - Educação: {$criterios['educacao']}

    Currículo:
    $textoCurriculo

    Responda se o currículo atende aos critérios e identifique os pontos fortes e fracos.
    ";

    $data = [
        "model" => "gpt-3.5-turbo",
        "messages" => [
            ["role" => "system", "content" => "Você é um assistente de análise de currículos."],
            ["role" => "user", "content" => $prompt]
        ],
        "max_tokens" => 500,
        "temperature" => 0.1,
    ];

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer $openaiApiKey",
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return "Erro ao conectar à API: " . curl_error($ch);
    }

    curl_close($ch);

    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? "Erro ao analisar o currículo.";
}

if ($_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
    $caminhoPdf = $_FILES['pdf']['tmp_name'];
    $textoCurriculo = extrairTextoPdf($caminhoPdf);

    if ($textoCurriculo) {
        $criterios = [
            "cargo" => $_POST['cargo'],
            "competencias" => explode(',', $_POST['competencias']),
            "experiencia" => $_POST['experiencia'],
            "educacao" => $_POST['educacao'],
        ];

        $resultado = analisarCurriculoComOpenAI($textoCurriculo, $criterios, $openaiApiKey);

        if ($resultado) {
            echo json_encode(["sucesso" => true, "mensagem" => $resultado]);
        } else {
            echo json_encode(["sucesso" => false, "erro" => "Erro ao analisar o currículo com a OpenAI."]);
        }
    } else {
        echo json_encode(["sucesso" => false, "erro" => "Erro ao extrair texto do PDF."]);
    }
} else {
    echo json_encode(["sucesso" => false, "erro" => "Erro no upload do arquivo."]);
}


?>