<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Тесты API</title></head>
<body style="font-family: Arial; padding: 20px;">
    <h1>Результаты тестирования API</h1>
    
    <?php
    function check($url, $expectedCode, $testName) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $isValidJson = json_decode($response) !== null;
        $codeOk = ($httpCode == $expectedCode);
        
        echo "<div style='margin-bottom: 10px; padding: 10px; border: 1px solid #ccc;'>";
        echo "<b>$testName</b><br>";
        echo $codeOk ? "✅ Статус $expectedCode: ПРОЙДЕН<br>" : "❌ Статус $expectedCode: ПРОВАЛ (получен $httpCode)<br>";
        
        if ($isValidJson) {
            echo "✅ Ответ — валидный JSON<br>";
        } else {
            echo "❌ Ответ НЕ является валидным JSON<br>";
        }
        echo "</div>";
    }

    check("http://localhost/api/index.php", 200, "Тест 1: Успешный запрос");
    check("http://localhost/api/index.php?limit=abc", 400, "Тест 2: Некорректный запрос (400)");
    check("http://localhost/api/index.php?error-test", 500, "Тест 3: Внутренняя ошибка (500)");
    ?>
</body>
</html>