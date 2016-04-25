Plugin zur Schlachtanmeldung

Benötigte Plugins:
-MyBB pluginlibrary 
-MyBB myleagues Plugin
-MyBB-Plugin-OPT-Armies

Dieses Plugin bietet die möglichkeit sich zu den myleagues Schlachten anzumelden und eine Übersicht über die Anmeldung zu generieren. Für die Struktur wird das MyBB-Plugin-OPT-Armies benötigt.



Menü zur Anmeldung:
$mybburl/misc.php?action=match_response //verwendet die lid des nächsten matches, alle zukünftigen matches werden angezeigt
oder
$mybburl/misc.php?action=match_response&lid={aktive myleagues lid} //alle zukünftigen matches werden angezeigt    Empfohlen
oder
$mybburl/misc.php?action=match_response&mid={myleagues match ID} //nur das eine match wird angezeigt


Menü zur Übersicht
$mybburl/misc.php?action=match_response_display //verwendet die lid des nächsten matches, die Rückmeldungen zum match werden angezeigt
oder
$mybburl/misc.php?action=match_response_display&lid={aktive myleagues lid} //die Rückmeldungen zum nächsten match der league werden angezeigt Empfohlen
oder
$mybburl/misc.php?action=match_response_display&mid={myleagues match ID} //Die Rückmeldungen zum match mit der passenden mid werden angezeigt 
