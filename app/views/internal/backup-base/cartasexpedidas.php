    <?php
        header('Content-Type: text/html; charset=UTF-8');
        include './../class/Connect.php';

        if (!isset($connection) || !($connection instanceof mysqli)) {
            die("Erro: conexão com o banco não foi inicializada.");
        }

        if (!$connection->set_charset('utf8mb4')) {
            die("Erro ao definir charset: " . $connection->error);
        }
        $databaseName = 'ios1_users';

        if (!mysqli_select_db($connection, $databaseName)) {
            die("Erro ao selecionar o banco de dados: " . mysqli_error($connection));
        }
        $consulta = $connection->query("SELECT * FROM CartasExpedidas ORDER BY Data DESC");
        
        if (!$consulta) {
            die("Erro na consulta: " . $connection->error);
        }
        
        $rows = array();
        $rows2 = array();        
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cartas Expedidas</title>
        <!-- CSS -->
        <link rel="stylesheet" href="./../css/pages.css">
        <link rel="stylesheet" href="./../css/structure.css">
        <link rel="stylesheet" href="./../css/form.css">
        <link rel="stylesheet" href="./../css/table.css">
        <!-- JAVASCRIPT -->
        <script type="text/javascript" src="./../js/common.js"></script>
        <title>Primeira Vara Crime</title>
    </head>
    <body class="page" data-theme="light">
        <div class="container">
            <div class="modal page full">
                <div class="left">
                    <span>vara | cartorio | lista</span>
                    <span class="title">Cartas Expedidas</span>
                </div>
                <div class="right">
                    <img src="./../img/icones/Folder-Text--Streamline-Ultimate.png" alt="Folder">
                </div>
            </div>
            <div class="layout">
                <div class="left">
                    <div class="modal form">
                        <div class="title-bar">
                            <span class="title-name">Novo cadastro</span>
                        </div>                    
                        <form class="document-create" name="docCreate" action="./../fast-request/crt-cartaexpedida-create.php" method="post">
                            <div class="form-line">
                                <div class="form-box">
                                    <label>Classe</label>
                                    <select class="pequeno" name="doc-class" id="doc-class" required>
                                        <option value="Carta Precatória" selected>Carta Precatória</option>
                                        <option value="Carta Rogatória">Carta Rogatória</option>
                                        <option value="Carta de Ordem">Carta de Ordem</option>                                    
                                    </select>
                                    <input class="pequeno" type="hidden" id="doc-user" name="doc-user">
                                </div>
                                <div class="form-box">
                                    <label>Finalidade</label>
                                    <select class="medio" name="doc-goal" id="doc-goal" required>
                                        <option value="" disabled selected>Selecione aqui</option>                                    
                                        <option value="Citação para Defesa Prévia">Citação para Defesa Prévia</option>
                                        <option value="Citação diversa">Citação diversa</option>
                                        <option value="Cumprimento de Alvará de Soltura">Cumprimento de Alvará de Soltura</option>
                                        <option value="Cumprimento de Mandado de Prisão">Cumprimento de Mandado de Prisão</option>
                                        <option value="Cumprimento de Sentença">Cumprimento de Sentença</option>
                                        <option value="Intimação de Decisão Interlocutória">Intimação de Decisão Interlocutória</option>
                                        <option value="Intimação da Sentença">Intimação da Sentença</option>
                                        <option value="Intimação para Audiência">Intimação para Audiência</option>
                                        <option value="Intimação diversa">Intimação diversa</option>                                    
                                        <option value="Notificação">Notificação</option>                                    
                                        <option value="Outros">Outros</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-line">        
                                <div class="form-box">
                                    <label>Número do Processo</label>
                                    <input class="medio" type="text" id="doc-process" name="doc-process" required>
                                    <input class="pequeno" type="hidden" id="inv-process" name="inv-process">
                                </div>
                                <div class="form-box">
                                    <label>Prazo</label>
                                    <select class="pequeno" name="doc-term" id="doc-term">
                                        <option value="10">10 dias</option>
                                        <option value="30">30 dias</option>
                                        <option value="60" selected>60 dias</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-line">
                                <div class="form-box">
                                    <label>Comarca destino</label>
                                    <input class="medio" type="text" id="doc-city" name="doc-city" required>
                                </div>
                                <div class="form-box">
                                    <label>Estado</label>
                                    <select class="pequeno" name="doc-state" id="doc-state" required>
                                        <option value="" selected disabled>Selecione aqui</option>
                                        <option value="AC">Acre</option>
                                        <option value="AL">Alagoas</option>
                                        <option value="AP">Amapá</option>
                                        <option value="AM">Amazonas</option>
                                        <option value="BA">Bahia</option>
                                        <option value="CE">Ceará</option>
                                        <option value="DF">Distrito Federal</option>
                                        <option value="ES">Espírito Santo</option>
                                        <option value="GO">Goiás</option>
                                        <option value="MA">Maranhão</option>
                                        <option value="MT">Mato Grosso</option>
                                        <option value="MS">Mato Grosso do Sul</option>
                                        <option value="MG">Minas Gerais</option>
                                        <option value="PA">Pará</option>
                                        <option value="PB">Paraíba</option>
                                        <option value="PR">Paraná</option>
                                        <option value="PE">Pernambuco</option>
                                        <option value="PI">Piauí</option>
                                        <option value="RJ">Rio de Janeiro</option>
                                        <option value="RN">Rio Grande do Norte</option>
                                        <option value="RS">Rio Grande do Sul</option>
                                        <option value="RO">Rondônia</option>
                                        <option value="RR">Roraima</option>
                                        <option value="SC">Santa Catarina</option>
                                        <option value="SP">São Paulo</option>
                                        <option value="SE">Sergipe</option>
                                        <option value="TO">Tocantins</option>
                                        <option value="EX">Estrangeiro</option>
                                    </select>
                                </div>
                            </div>                        
                            <div class="form-line">
                                <div class="form-box">
                                    <button type="submit" id="create" class="primary" disabled>Cadastrar documento</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal form">
                        <div class="title-bar">
                            <span class="title-name">Cartas em andamento</span>
                        </div>
                        <div class="table">                        
                            <?php
                                while ($row = $consulta->fetch_assoc()) {
                                $rows[] = $row;
                                $processo = $row['Processo'];
                            ?>
                            <div class="table-block" id="<?php echo $row['IdCarta']; ?>">
                                <div class="table-row header">
                                    <div class="block-line">
                                        <!-- BLOCK ETAPAS -->
                                        <div class="table-cell etapas">
                                            <?php if($row['Status'] == "aguardando assinatura"){?>
                                                <div class="etapa-inicio current">aguardando assinatura</div>
                                                <div class="etapa await"></div>
                                                <div class="etapa await"></div>
                                                <div class="etapa await"></div>
                                                <div class="etapa await"></div>
                                                <div class="etapa-fim await"></div>
                                            <?php } else if($row['Status'] == "aguardando envio"){?>
                                                <div class="etapa-inicio finished"></div>
                                                <div class="etapa current">aguardando envio</div>
                                                <div class="etapa await"></div>
                                                <div class="etapa await"></div>
                                                <div class="etapa await"></div>
                                                <div class="etapa-fim await"></div>
                                            <?php } else if($row['Status'] == "aguardando juntada de termo"){?>
                                                <div class="etapa-inicio finished"></div>
                                                <div class="etapa finished cap1"></div>
                                                <div class="etapa finished cap2"></div>
                                                <div class="etapa current">aguardando juntada do termo de envio</div>
                                                <div class="etapa await"></div>
                                                <div class="etapa-fim await"></div>
                                            <?php } else { ?>
                                                <div class="etapa-inicio finished"></div>
                                                <div class="etapa finished cap1"></div>
                                                <div class="etapa finished cap2"></div>
                                                <div class="etapa finished cap3"></div>
                                                <div class="etapa current sleep">aguardando cumprimento</div>
                                                <div class="etapa-fim await"></div>
                                            <?php } ?>
                                        </div>
                                        <!-- END BLOCK -->
                                    </div>                                
                                    <div class="block-line">
                                        <div class="table-cell" id="doc-info">Document Id:</div>
                                        <div class="table-cell" id="doc-id"><?php echo $row['IdCarta']; ?></div>
                                    </div>
                                </div>
                                <div class="table-row body">
                                    <div class="block-box process">
                                        <div class="table-cell" id="doc-process-title">Processo local</div>
                                        <div class="table-cell" id="doc-process"><?php echo $row['Processo']; ?></div>
                                    </div>
                                    <div class="block-box description">
                                        <div class="table-cell" id="doc-description-title">Finalidade</div>
                                        <div class="table-cell" id="doc-description"><?php echo $row['Finalidade']; ?></div>
                                    </div>                                
                                    <div class="block-box prazo">
                                        <div class="table-cell" id="doc-term-title">Prazo</div>
                                        <div class="table-cell" id="doc-term"><?php echo $row['Prazo']; ?> dias</div>
                                    </div>
                                    <div class="block-box city">
                                        <div class="table-cell" id="doc-city-title">Comarca de destino</div>
                                        <div class="table-cell" id="doc-city"><?php echo $row['Comarca'] . " - " . $row['Estado'] ?></div>
                                        <?php if($row['Status'] == 'aguardando envio'){?>
                                            <div class="table-cell" style="margin-top: -7px; font-size: 10px; color: crimson;">
                                            <?php
                                                if($row['Estado'] == 'GO' || $row['Estado'] == 'PE' || $row['Estado'] == 'RJ' || $row['Estado'] == 'RS' || $row['Estado'] == 'RR' || $row['Estado'] == 'SC' || $row['Estado'] == 'SE' || $row['Estado'] == 'TO' || $row['Estado'] == 'MS'){
                                                    echo "enviar pelo Malote Digital";
                                                } else if($row['Estado'] == 'ES' || $row['Estado'] == 'AP' || $row['Estado'] == 'DF' || $row['Estado'] == 'MA' || $row['Estado'] == 'MT' || $row['Estado'] == 'PA' || $row['Estado'] == 'PB' || $row['Estado'] == 'RN' || $row['Estado'] == 'RO' || $row['Estado'] == 'PI' || $row['Estado'] == 'MG'){
                                                    echo "enviar pelo PJe";
                                                } else if($row['Estado'] == 'BA'){
                                                    $comarcasCCM = ['Alagoinhas', 'Barreiras', 'Bom Jesus da Lapa', 'Brumado', 'Camaçari', 'Eunápolis', 'Feira de Santana', 'Guanambi', 'Ilhéus', 'Irecê', 'Itabuna', 'Jacobina', 'Jequié', 'Juazeiro', 'Lauro de Freitas', 'Paulo Afonso', 'Porto Seguro', 'Santo Antônio de Jesus', 'Senhor do Bonfim', 'Simões Filho', 'Teixeira de Freitas', 'Valença', 'Vitória da Conquista'];
                                                    $comarcasVCCM = ['Andorinha', 'Angical', 'Anguera', 'Apuarema', 'Araças', 'Aracatu', 'Aramari', 'Barro Preto', 'Caém', 'Cairu', 'Cândiba', 'Cristópolis', 'Dom Macedo Costa', 'Glória', 'Ibititá'];
                                                    if(in_array($row['Comarca'], $comarcasCCM)){
                                                        echo "Comarca com Central de Mandados (CCM)";
                                                    } else if (in_array($row['Comarca'], $comarcasVCCM)){
                                                        echo "Comarca vinculada a uma Central de Mandados (CCM)";
                                                    } else {
                                                        echo "enviar pelo PJe";
                                                    }
                                                } else {
                                                    echo "enviar pelo e-SAJ / e-mail";
                                                }
                                            ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>                             
                                <div class="table-row-mov">
                                    <!-- CONSULTA PHP -->
                                    <?php                                    
                                        $stmt = $connection->prepare("SELECT * FROM `CartasExpedidasMov` WHERE `IdCarta` = ? ORDER BY Data ASC LIMIT 5");
                                        if ($stmt) {
                                            $stmt->bind_param("s", $row['IdCarta']);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            while ($row2 = $result->fetch_assoc()) {
                                                $rows2[] = $row2;
                                                $status = "";
                                                if($row2['Movimento'] == "aguardando envio"){
                                                    $status = "Assinatura confirmada em ";
                                                } else if($row2['Movimento'] == "aguardando juntada de termo"){
                                                    $status = "Enviada em ";                                    
                                                } else if($row2['Movimento'] == "aguardando cumprimento"){
                                                    $status = "Termo juntado em ";
                                                } else {
                                                    $status = "Cadastrada em ";
                                                }
                                                $data_hora = $row2['Data'];
                                                $data_formatada = date("d/m/Y \à\s H:i:s", strtotime($data_hora));                                                                
                                    ?>
                                    <div class="table-cell" id="<?php echo $row2['IdMov'];?>"><?php echo $status . $data_formatada . " por " . $row2['Usuario']; ?></div>
                                    <?php
                                            }
                                            $stmt->close();
                                        }
                                    ?>
                                </div>
                            </div>
                            <?php
                                }
                                $connection->close();
                            ?>                        
                        </div>
                    </div>
                </div>
                <div class="right">
                    <div class="modal form">
                        <div class="title-bar">
                            <span class="title-name">Informar movimentação</span>
                        </div>
                        <form name="passAltera" action="./../fast-request/crt-cartaexpedida-mov.php" method="post">
                            <div class="form-line">
                                <div class="form-box">
                                    <label for="mov-process">Processo</label>
                                    <select class="pequeno" name="mov-process" id="mov-process" required>
                                        <option value="" disabled selected>Selecione aqui</option>
                                        <?php
                                            foreach ($rows as $row) {
                                                echo "<option value='" . $row['IdCarta'] . "'>" . $row['Processo'] . " - " . $row['Comarca'] . "</option>";
                                            }
                                        ?>
                                    </select>
                                    <input class="pequeno" type="hidden" id="mov-user" name="mov-user">
                                </div>
                            </div>
                            <div class="form-line">
                                <div class="form-box">
                                    <label for="mov-type">Tipo de movimentação</label>
                                    <select class="pequeno" name="mov-type" id="mov-type" required aria-label="Tipo de movimentação">
                                        <option value="" disabled selected>Selecione aqui</option>
                                        <!-- Opções serão populadas dinamicamente via JavaScript -->
                                    </select>
                                </div>
                            </div>
                            <div class="form-line">
                                <div class="form-box">
                                    <button type="submit" id="sendMov" class="primary" disabled>Movimentar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div id="customAlertModal" class="modal-custom" style="display:none;">
            <div class="modal-content-custom">
                <span class="close-button-custom">&times;</span>
                <h2 id="customAlertTitle"></h2>
                <p id="customAlertMessage"></p>                
            </div>
        </div>

        <style>
            /* CSS básico para o modal */
            .modal-custom {
                display: none; /* Escondido por padrão */
                position: fixed; /* Fica por cima de tudo */
                z-index: 1000; /* Garante que esteja na frente */
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto; /* Habilita scroll se o conteúdo for muito grande */
                background-color: rgba(0,0,0,0.4); /* Fundo escuro semi-transparente */
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .modal-content-custom {
                background-color: #fefefe;
                margin: auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 500px;
                border-radius: 7px;
                box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19);
                animation-name: animatetop;
                animation-duration: 0.4s;
                text-align: center;
            }
            .close-button-custom {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
            }
            .close-button-custom:hover,
            .close-button-custom:focus {
                color: black;
                text-decoration: none;
                cursor: pointer;
            }
            @keyframes animatetop {
                from {top: -300px; opacity: 0}
                to {top: 0; opacity: 1}
            }
        </style>

        <script>
            // Função JavaScript para exibir o alerta personalizado
            function showCustomAlert(title, message) {
                const modal = document.getElementById('customAlertModal');
                const modalTitle = document.getElementById('customAlertTitle');
                const modalMessage = document.getElementById('customAlertMessage');
                const closeButton = document.querySelector('.close-button-custom');
                const okButton = document.getElementById('customAlertOkButton');

                modalTitle.textContent = title;
                modalMessage.textContent = message;
                modal.style.display = 'flex'; // Mostra o modal

                function closeModal() {
                    modal.style.display = 'none';
                    closeButton.removeEventListener('click', closeModal);
                    okButton.removeEventListener('click', closeModal);
                    window.removeEventListener('click', outsideClick);
                }

                function outsideClick(event) {
                    if (event.target == modal) {
                        closeModal();
                    }
                }

                closeButton.addEventListener('click', closeModal);
                okButton.addEventListener('click', closeModal);
                window.addEventListener('click', outsideClick);
            }

            // Exemplo de uso:
            // Substitua alert("Selecione uma comarca válida."); por:
            // showCustomAlert("Erro de Validação", "Selecione uma comarca válida.");
        </script>

        <script type="text/javascript" src="./../js/request.js"></script>
        <script type="text/javascript" src="./../js/forms.js"></script>
        <script>
            // Cria um objeto JS com os tipos por processo
            const tiposPorProcesso = {
                <?php
                    $total = count($rows);
                    $i = 0;
                    foreach ($rows as $row):
                        $i++;
                        $tipos = [];
                        switch ($row['Status']) {
                            case 'aguardando assinatura':
                                $tipos[] = ["value" => "aguardando envio", "label" => "Informar assinatura da Juíza"];
                                break;
                            case 'aguardando envio':
                                $tipos[] = ["value" => "aguardando juntada de termo", "label" => "Informar envio"];
                                break;
                            case 'aguardando juntada de termo':
                                $tipos[] = ["value" => "aguardando cumprimento", "label" => "Informar juntada de Termo de envio"];
                                break;
                            case 'aguardando cumprimento':
                                $tipos[] = ["value" => "excluir", "label" => "Informar juntada de Carta devolvida"];
                                break;
                        }
                        $tipos[] = ["value" => "delete", "label" => "Excluir"];
                        $key = $row['IdCarta'];
                        $json = json_encode($tipos, JSON_UNESCAPED_UNICODE);
                        echo '"' . $key . '": ' . $json;
                        if ($i < $total) echo ",";
                ?>
                <?php endforeach; ?>
            };
        </script>
        <script>
            document.querySelector("select#doc-class")?.addEventListener("change", function(){
                verificaDados();
            });
            document.querySelector("select#doc-goal")?.addEventListener("change", function(){
                verificaDados();
            });
            document.querySelector("select#doc-term")?.addEventListener("change", function(){
                verificaDados();
            });
            document.querySelector("select#doc-state")?.addEventListener("change", function(){            
                verificaDados();
            });
            // Listas de comarcas
            const comarcasCCM = [
                'Alagoinhas', 'Barreiras', 'Bom Jesus da Lapa', 'Brumado', 'Camaçari', 'Eunápolis', 'Eunapolis',
                'Feira de Santana', 'Guanambi', 'Ilhéus', 'Ilheus', 'Irecê', 'Irece', 'Itabuna', 'Jacobina', 'Jequié', 'Jequie',
                'Juazeiro', 'Lauro de Freitas', 'Paulo Afonso', 'Porto Seguro', 'Santo Antônio de Jesus', 'Santo Antonio de Jesus',
                'Senhor do Bonfim', 'Simões Filho', 'Simoes Filho', 'Teixeira de Freitas', 'Valença', 'Valenca',
                'Vitória da Conquista', 'Vitoria da Conquista', 'Salvador'
            ];
            const comarcasVCCM = [
                'Andorinha', 'Angical', 'Anguera', 'Apuarema', 'Araças', 'Aracas', 'Aracatu', 'Aramari',
                'Barro Preto', 'Caém', 'Caem', 'Cairu', 'Cndiba', 'Candiba', 'Cristopolis', 'Cristópolis',
                'Dom Macedo Costa', 'Glória', 'Gloria', 'Ibititá', 'Ibitita', 'Itagi', 'Itagimirim', 'Itapé', 'Itape',
                'Itapebi', 'Jussara', 'Madre de Deus', 'Malhada de Pedras', 'Manoel Vitorino', 'Mirangaba', 'Ourolândia', 'Ourolandia',
                'Paratinga', 'Pindaí', 'Pindai', 'Presidente Dutra', 'Presidente Tancredo Neves', 'Tancredo Neves',
                'Santa Brígida', 'Santa Brigida', 'São Gabriel', 'Sao Gabriel', 'Serra do Ramalho', 'Serra Preta', 'Serrolândia', 'Serrolandia',
                'Sítio do Mato', 'Sitio do Mato', 'Uibaí', 'Uibai', 'Umburanas', 'Várzea Nova', 'Varzea Nova', 'Varzedo'
            ];
            const isComarcaInList = (comarca, list) => list.includes(comarca);
            document.querySelector("#doc-city")?.addEventListener("change", function () {
                const cityInput = this;
                if (typeof formatarCidade === "function") {
                    formatarCidade(cityInput);
                }
                const selectedComarca = cityInput.value?.trim();
                if (!selectedComarca) {
                    showCustomAlert("Erro de Validação", "Selecione uma comarca válida.");
                    return;
                }
                if (isComarcaInList(selectedComarca, comarcasCCM)) {
                showCustomAlert("Informação", "Comarca com Central de Mandados (CCM).");
                    this.value = "";
                    document.querySelector("#create").disabled = true;
                    return;
                }
                if (isComarcaInList(selectedComarca, comarcasVCCM)) {
                    showCustomAlert("Informação", "Comarca vinculada a uma Central de Mandados (CCM).");
                    this.value = "";
                    document.querySelector("#create").disabled = true;
                    return;
                }
                verificaDados();
            });
            document.querySelector("#doc-process")?.addEventListener("change", function() {
                var docProcess = document.querySelector("#doc-process");
                if (typeof validarNumeroProcesso === "function") {
                    validarNumeroProcesso(docProcess);
                }
                // Informar nome do usuário atual para validação de movimentação
                try {
                    var user = window.parent?.document?.querySelector(".partial-name")?.textContent?.trim() || "";
                    document.querySelector("#doc-user").value = user;
                } catch (e) {}
                verificaDados();
            });
            document.querySelector("#mov-process")?.addEventListener("change", function () {
                const selectProcess = document.querySelector("#mov-process");
                const selectType = document.querySelector("#mov-type");
                const sendButton = document.querySelector("#sendMov");
                if (!selectProcess || !selectType || !sendButton) {
                    console.error("Um ou mais elementos não encontrados no DOM.");
                    return;
                }
                selectType.innerHTML = "";
                const optPadrao = document.createElement("option");
                optPadrao.value = "";
                optPadrao.textContent = "Selecione aqui";
                optPadrao.disabled = true;
                optPadrao.selected = true;
                selectType.appendChild(optPadrao);
                const idProcesso = selectProcess.value;
                if (idProcesso && tiposPorProcesso[idProcesso]) {
                    tiposPorProcesso[idProcesso].forEach(function (tipo) {
                        const opt = document.createElement("option");
                        opt.value = tipo.value;
                        opt.textContent = tipo.label;
                        selectType.appendChild(opt);
                    });
                }
                sendButton.disabled = true;
            });
            document.querySelector("#mov-type")?.addEventListener("change", function () {
                const selectProcess = document.querySelector("#mov-process");
                const selectType = document.querySelector("#mov-type");
                const sendButton = document.querySelector("#sendMov");
                const movUserInput = document.querySelector("#mov-user");
                const valueProcess = selectProcess.value?.trim();
                const valueType = selectType.value?.trim();
                let user = "";
                try {
                    user = window.parent?.document?.querySelector(".partial-name")?.textContent?.trim() || "";
                } catch (e) {}
                movUserInput.value = user;
                sendButton.disabled = !(valueProcess && valueType);
            });
            function verificaDados() {
                const selectClasse = document.querySelector("#doc-class");
                const selectFinalidade = document.querySelector("#doc-goal");
                const selectPrazo = document.querySelector("#doc-term");
                const selectEstado = document.querySelector("#doc-state");
                const inputProcesso = document.querySelector("#doc-process");
                const inputComarca = document.querySelector("#doc-city");
                const btnCreate = document.querySelector("#create");
                if (!selectClasse || !selectFinalidade || !selectPrazo || !selectEstado || !inputProcesso || !inputComarca || !btnCreate) {
                    console.error("Um ou mais elementos do formulário não foram encontrados.");
                    return;
                }
                // Mantém doc-user sempre preenchido quando possível
                try {
                    const user = window.parent?.document?.querySelector(".partial-name")?.textContent?.trim() || "";
                    const docUser = document.querySelector("#doc-user");
                    if (docUser) docUser.value = user;
                } catch (e) {}
                const valueClasse = selectClasse.value?.trim();
                const valueFinalidade = selectFinalidade.value?.trim();
                const valuePrazo = selectPrazo.value?.trim();
                const valueEstado = selectEstado.value?.trim();
                const valueProcesso = inputProcesso.value?.trim();
                const valueComarca = inputComarca.value?.trim();
                const allFilled = [
                    valueClasse,
                    valueFinalidade,
                    valuePrazo,
                    valueEstado,
                    valueProcesso,
                    valueComarca
                ].every(value => value && value.length > 0);
                btnCreate.disabled = !allFilled;
            }
        </script>
    </body>
    </html>
