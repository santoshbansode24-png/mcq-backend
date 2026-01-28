$headers = @{ "Content-Type" = "application/json" }
$body = @{
    user_id    = 999
    chapter_id = 888
    set_index  = 0
    type       = "mcq"
    score      = 10
    total      = 10
} | ConvertTo-Json

Invoke-RestMethod -Uri "http://localhost/veeru/backend/api/mark_set_completed.php" -Method Post -Headers $headers -Body $body

Invoke-RestMethod -Uri "http://localhost/veeru/backend/api/get_set_status.php?user_id=999&chapter_id=888&type=mcq" -Method Get
