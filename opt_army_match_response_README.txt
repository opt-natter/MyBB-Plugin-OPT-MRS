Plugin zur Schlachtanmeldung

Custom Menus:
Neuer Eintrag 

Menü zur Anmeldung:
$mybburl/misc.php?action=match_response //verwendet die lid des nächsten matches, alle zukünftigen matches werden angezeigt
oder
$mybburl/misc.php?action=match_response&lid={aktive myleagues lid} //alle zukünftigen matches werden angezeigt
oder
$mybburl/misc.php?action=match_response&mid={myleagues  match ID} //nur das eine match wird angezeigt


Menü zur Übersicht
$mybburl/misc.php?action=match_response_display //verwendet die lid des nächsten matches, die Rückmeldungen zum match werden angezeigt
oder
$mybburl/misc.php?action=match_response_display&lid={aktive myleagues lid} //die Rückmeldungen zum nächsten match der league werden angezeigt
oder
$mybburl/misc.php?action=match_response_display&mid={myleagues match ID} //Die Rückmeldungen zum match mit der passenden mid werden angezeigt
