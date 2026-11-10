<?php
// TeamController.php - Team endpoints are unavailable until their game logic is implemented.

class TeamController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/ATeam/getPlayerTeam':
            case '/api/ATeam/playerOnline':
            case '/api/ATeam/create':
            case '/api/ATeam/disbandTeam':
            case '/api/ATeam/playerTeamLeave':
            case '/api/ATeam/playerTeamResign':
            case '/api/ATeam/fireTeamMember':
            case '/api/ATeam/changeTeamRelation':
            case '/api/ATeam/modifyTeamWord':
            case '/api/ATeam/getTeamRank':
            case '/api/ATeam/searchTeamByName':
            case '/api/ATeam/showTeamReview':
            case '/api/ATeamInvReq/SendPlayerInv':
            case '/api/ATeamInvReq/acceptTeamInv':
            case '/api/ATeamInvReq/rejectTeamInv':
            case '/api/ATeamInvReq/cancelTeamInv':
            case '/api/ATeamInvReq/sendTeamJoinRequest':
            case '/api/ATeamInvReq/acceptTeamJoinReq':
            case '/api/ATeamInvReq/cancelTeamJoinReq':
                self::notImplemented();
                return true;
        }
        return false;
    }

    private static function notImplemented() {
        http_response_code(501);
        sendJson(['state' => 'error_not_implemented']);
    }
}
