<?php
/*
Criado por: Ronaldo Sacco - ronaldo@saperx.com.br

Script criado para facilitar a comunicacao entre ZABBIX e ASTERISK, utilizando AMI (Asterisk Manager Interface).
Este script monitora:

                "peerstatus peer   - Retorna o status de um determinado peer";
                "peertime peer     - Retorna o tempo (qualify) de um determinado peer";
                "activecalls       - Retorna quantas chamadas estao em curso";
                "longestcall       - Retorna o valor em minutos da chamada com mais tempo em duracao";
                "onlinepeers       - Retorna a quantidade de SIP online";

Modo de execucao:
php ast_zabbix.php COMANDO SIP_PEER

Exemplo:
php ast_zabbix sipstatus 200

*/


/*
Credenciais do manager
Deve ser configurado as variaveis $MANAGER_ que seguem abaixo.
*/

$MANAGER_user = 'ast-zabbix';
$MANAGER_pass = 'Zero5292';
$MANAGER_host = '127.0.0.1';
$MANAGER_port = 5038;


/*
Inicio do codigo. 
Nao alterar nada daqui para baixo.
*/

function manager_connect() {
        global $MANAGER_host, $MANAGER_user, $MANAGER_port, $MANAGER_pass;

        $manager_connection_timeout = 3; //segundos

        //Conexao com manager
        $socket = fsockopen($MANAGER_host, $MANAGER_port, $errno, $errstr, $manager_connection_timeout);

        if (!$socket) {
                echo "ERRO na conexao com manager\n";
                exit(1);
        } else {
                $login = "Action: login\r\n";
                $login .= "Username: " . $MANAGER_user . "\r\n";
                $login .= "Secret: " . $MANAGER_pass . "\r\n";
                $login .= "Events: Off\r\n";
                $login .= "\r\n";
                fwrite($socket, $login);

                //Coletando primeiras linhas
                $manager_version = fgets($socket);
                $cmd_response = fgets($socket);
                $response = fgets($socket);
                $blank_line = fgets($socket);

                if (substr($response, 0, 9) == "Message: ") {
                        /* We have got a response */
                        $loginresponse = trim(substr($response, 9));
                        if ($loginresponse != "Authentication Accepted" && $loginresponse != "Authentication accepted") {
                                echo("-- Unable to log in: $loginresponse\n");
                                fclose($socket);
                                exit(1);
                        } else {
				return $socket;
			}
		}else{
                        echo "Unexpected response: $response\n";
                        fclose($socket);
			exit(0);
                }
        }


}

function peerstatus($peer,$search) {
	//Returns a number of qualify OR peer status

	$socket = manager_connect();
	$checkpeer = "Action: SIPpeerstatus\r\n";
	$checkpeer .= "Peer: $peer\r\n";
	$checkpeer .= "\r\n";
	fwrite($socket, $checkpeer);

	$count = 0;
	$line=NULL;
	while ($line != "Event: SIPpeerstatusComplete") {
		//echo $line . "\n";

		if($line=="Response: Error"){
			echo $line."\nPeer existe?\n";
			fclose($socket);
			exit(0);
		}

		$value = explode(":",$line);

		if($value[0]==$search){
			echo ltrim($value[1])."\n";
			fclose($socket);
			exit;
		}

		if($count++==1000){
			echo "Algum erro ocorreu. Loop. Finalizando\n";
			exit(1);
		}

		$line = trim(fgets($socket));
	}

	fclose($socket);
}

function onlinepeers() {
        //Returns a number of online peers

        $socket = manager_connect();
        $checkpeer = "Action: SIPpeerstatus\r\n";
        $checkpeer .= "\r\n";
        fwrite($socket, $checkpeer);

        $count = 0;
        $line=NULL;
	$onlinepeer=0;
        while ($line != "Event: SIPpeerstatusComplete") {
                //echo $line . "\n";

                if($line=="Response: Error"){
                        echo $line."\nPeer existe?\n";
                        fclose($socket);
                        exit(0);
                }

                $value = explode(":",$line);

                if($line=="PeerStatus: Reachable"){
			$onlinepeer++;
                }

                if($count++==1000){
                        echo "Algum erro ocorreu. Loop. Finalizando\n";
                        exit(1);
                }

                $line = trim(fgets($socket));
        }
	
	echo $onlinepeer."\n";

        fclose($socket);
}

function activecalls(){
	//Returns a number of active calls
        //Returns a number of qualify OR peer status

        $socket = manager_connect();
        $checkpeer = "Action: CoreStatus\r\n";
        $checkpeer .= "\r\n";
        fwrite($socket, $checkpeer);

        $count = 0;
        $line=NULL;
        $line = trim(fgets($socket));

        do {
                $line = trim(fgets($socket));

                //echo $line . "\n";

                if($line=="Response: Error"){
                        echo $line."\n";
                        fclose($socket);
                        exit(0);
                }

                $value = explode(":",$line);

                if($value[0]=="CoreCurrentCalls"){
                        echo ltrim($value[1])."\n";
                        fclose($socket);
                        exit;
                }

                $value = explode(":",$line);


                if($count++==1000){
                        echo "Algum erro ocorreu. Loop. Finalizando\n";
                        exit(1);
                }

        } while(substr($line,0,16) != "CoreCurrentCalls");

        fclose($socket);

}

function asteriskrunning(){
	//Return 1 = Asterisk is running
	//	 0 = Asterisk is not running
	
	$ret = exec("/bin/ps -A | grep asterisk | wc -l");
	if($ret>0)
		echo "1\n";
	else
		echo "0\n";
}

function longestcall(){
	//Return the longest call, in seconds, running on asterisk

        $socket = manager_connect();
        $checkpeer = "Action: CoreShowChannels\r\n";
        $checkpeer .= "\r\n";
        fwrite($socket, $checkpeer);

        $count = 0;
        $line=NULL;
        $line = trim(fgets($socket));
	$seconds = 0;
        do {
                $line = trim(fgets($socket));

                //echo $line . "\n";

                if($line=="Response: Error"){
                        echo $line."\n";
                        fclose($socket);
                        exit(0);
                }

                $value = explode(":",$line);

                if($value[0]=="Duration"){
			//HH:MM:SS em segundos
			$tempo = ltrim($value[1])*60*60 + $value[2]*60 + $value[3];

			if($tempo > $seconds)
				$seconds=$tempo;
                }

                $value = explode(":",$line);

                if($count++==1000){
                        echo "Algum erro ocorreu. Loop. Finalizando\n";
                        exit(1);
                }

        } while($line != "EventList: Complete");

	echo $seconds."\n";

        fclose($socket);
}

if(!isset($argv[1])){
	echo "Parametros necessarios. Digite help para saber mais.\n";
	exit;
}

switch($argv[1]){
	case 'peerstatus':
		peerstatus($argv[2],"PeerStatus");
		break;
	case 'peertime':
		peerstatus($argv[2],"Time");
		break;
	case 'activecalls':
                activecalls();
                break;
	case 'asteriskrunning':
		asteriskrunning();
		break;
	case 'longestcall':
		longestcall();
		break;
	case 'onlinepeers':
		onlinepeers();
		break;
	case '--help':
	case '?':
		echo "Help\n\n";
                echo "peerstatus peer   - Retorna o status de um determinado peer\n";
                echo "peertime peer     - Retorna o tempo (qualify) de um determinado peer\n";
                echo "activecalls       - Retorna quantas chamadas estao em curso\n";
                echo "asteriskrunning   - Retorna 1 se asterisk estiver rodando. Senao 0\n";
                echo "longestcall       - Retorna o valor em minutos da chamada com mais tempo em duracao\n";
                echo "onlinepeers       - Retorna a quantidade de SIP online\n";
                echo "\n";
                break;

	Default:
		echo "Comando ".$argv[1]." não encontrado. Digite --help ou ? para saber mais\n\n";
		break;
}
