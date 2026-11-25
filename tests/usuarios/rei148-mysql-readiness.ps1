function Test-Rei148MySqlReady {
    param([scriptblock]$GetPid1, [scriptblock]$ProbeSql)

    try {
        $pidOutput = @(& $GetPid1)
        if ($pidOutput.Count -ne 1 -or ([string]$pidOutput[0]).Trim() -cne 'mysqld') { return $false }
        $sqlOutput = @(& $ProbeSql)
        return ($sqlOutput.Count -eq 1 -and ([string]$sqlOutput[0]).Trim() -ceq '1')
    } catch {
        return $false
    }
}
