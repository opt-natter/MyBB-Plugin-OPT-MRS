Plugin zur Schlachtanmeldung

Ben�tigte Plugins:
-MyBB pluginlibrary 
-MyBB myleagues Plugin
-MyBB-Plugin-OPT-Armies

Dieses Plugin bietet die m�glichkeit sich zu den myleagues Schlachten anzumelden und eine �bersicht �ber die Anmeldung zu generieren. F�r die Struktur wird das MyBB-Plugin-OPT-Armies ben�tigt.



Men� zur Anmeldung:
$mybburl/misc.php?action=match_response //verwendet die lid des n�chsten matches, alle zuk�nftigen matches werden angezeigt
oder
$mybburl/misc.php?action=match_response&lid={aktive myleagues lid} //alle zuk�nftigen matches werden angezeigt    Empfohlen
oder
$mybburl/misc.php?action=match_response&mid={myleagues match ID} //nur das eine match wird angezeigt


Men� zur �bersicht
$mybburl/misc.php?action=match_response_display //verwendet die lid des n�chsten matches, die R�ckmeldungen zum match werden angezeigt
oder
$mybburl/misc.php?action=match_response_display&lid={aktive myleagues lid} //die R�ckmeldungen zum n�chsten match der league werden angezeigt Empfohlen
oder
$mybburl/misc.php?action=match_response_display&mid={myleagues match ID} //Die R�ckmeldungen zum match mit der passenden mid werden angezeigt 
