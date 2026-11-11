<?php
// MarketController.php - Market offers, limited matching, and city resource transfers.

class MarketController {
    private static function parseUnitPriceScaled($value): ?int {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        if (!preg_match('/\A(0|[1-9][0-9]{0,5})(?:\.([0-9]{1,4}))?\z/', (string)$value, $matches)) {
            return null;
        }
        $fraction = str_pad($matches[2] ?? '', 4, '0');
        $scaled = ((int)$matches[1] * 10000) + (int)$fraction;
        return $scaled > 0 ? $scaled : null;
    }

    private static function calculateOfferValue(int $amount, int $unitPriceScaled, bool $roundUp): ?int {
        if ($amount <= 0 || $unitPriceScaled <= 0
            || $unitPriceScaled > intdiv(ResourceService::MAX_CITY_BALANCE * 10000, $amount)) {
            return null;
        }
        $scaledValue = $amount * $unitPriceScaled;
        return $roundUp
            ? intdiv($scaledValue + 9999, 10000)
            : intdiv($scaledValue, 10000);
    }

    private static function calculateFee(int $value): int {
        return intdiv(($value * 75) + 9999, 10000);
    }

    private static function rejectResourceLimit(PDO $db, Throwable $error): void {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($error instanceof ResourceLimitException) {
            sendJson(['state' => 'error_resource_limit', 'msg' => 'سيؤدي الطلب إلى تجاوز الحد الأقصى للموارد في المدينة']);
        }
        throw $error;
    }

    public static function handle($uri, $db) {
        $marketUris = [
            '/api/ACityMarket/getOffersList',
            '/api/AMarket/getOffersList',
            '/api/ACityMarket/getCityOffers',
            '/api/ACityMarket/proposeBuyOffer',
            '/api/ACityMarket/proposeSellOffer',
            '/api/AMarket/proposeOffer',
            '/api/ACityMarket/cancelMyOffer',
            '/api/ACityMarketTrans/transportResource',
            '/api/ACityMarketTrans/getCityTransportResource',
            '/api/ACityMarketTrans/speedUpTransport',
            '/api/ACityMarket/speedUpDealTrans',
            '/api/ACityMarket/getCityOffersTrans',
        ];
        if (!in_array($uri, $marketUris, true)) {
            return false;
        }

        switch ($uri) {
            case '/api/ACityMarket/getOffersList':
            case '/api/AMarket/getOffersList':
                self::getOffersList($db);
                return true;
            case '/api/ACityMarket/getCityOffers':
                self::getCityOffers($db);
                return true;
            case '/api/ACityMarket/proposeBuyOffer':
                self::proposeBuyOffer($db);
                return true;
            case '/api/ACityMarket/proposeSellOffer':
            case '/api/AMarket/proposeOffer':
                self::proposeSellOffer($db);
                return true;
            case '/api/ACityMarket/cancelMyOffer':
                self::cancelMyOffer($db);
                return true;
            case '/api/ACityMarketTrans/transportResource':
                self::transportResource($db);
                return true;
            case '/api/ACityMarketTrans/getCityTransportResource':
                self::getCityTransportResource($db);
                return true;
            case '/api/ACityMarketTrans/speedUpTransport':
            case '/api/ACityMarket/speedUpDealTrans':
                self::speedUpTransport($db);
                return true;
            case '/api/ACityMarket/getCityOffersTrans':
                self::getCityOffersTrans($db);
                return true;
        }
        return false;
    }

    public static function assertSchemaReady(PDO $db): void {
        $db->query('SELECT `id_offer`, `id_player`, `id_city`, `offer_type`, `res_type`, `amount`, `price_per_unit`, `done`, `status`, `created_at` FROM `market_offer` LIMIT 0');
        $db->query('SELECT `id_trans`, `id_player`, `id_city_from`, `id_city_to`, `resource`, `amount`, `unit_price`, `time_start`, `time_arrive`, `status` FROM `city_market_trans` LIMIT 0');
    }

    private static function getPlayer($db) {
        return requireAuthenticatedPlayer($db);
    }

    public static function formatCityOffers($db, $cityId) {
        $stmt = $db->prepare("SELECT mo.* FROM `market_offer` mo
                              INNER JOIN `city` c ON c.`id_city` = mo.`id_city` AND c.`id_player` = mo.`id_player`
                              WHERE mo.`id_city` = ? AND mo.`status` = 'active' AND (mo.`amount` - mo.`done`) > 0
                              ORDER BY mo.`id_offer` DESC");
        $stmt->execute([$cityId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $res = [];
        foreach ($rows as $r) {
            $res[] = [
                'id_deal'    => (int)$r['id_offer'],
                'resource'   => $r['res_type'],
                'amount'     => (int)$r['amount'],
                'done'       => (int)($r['done'] ?? 0),
                'unit_price' => (float)$r['price_per_unit'],
                'deal'       => $r['offer_type']
            ];
        }
        return $res;
    }

    public static function getOffersList($db) {
        $offerFor = isset($_REQUEST['offerFor']) ? trim($_REQUEST['offerFor']) : 'food';
        if (!in_array($offerFor, ['food', 'wood', 'stone', 'metal'])) {
            $offerFor = 'food';
        }

        // Buy offers: Tenders ordered by highest bidding price first (unit_price DESC)
        $bStmt = $db->prepare("SELECT mo.* FROM `market_offer` mo
                               INNER JOIN `city` c ON c.`id_city` = mo.`id_city` AND c.`id_player` = mo.`id_player`
                               WHERE mo.`res_type` = ? AND mo.`offer_type` = 'buy' AND mo.`status` = 'active'
                                 AND mo.`id_player` > 0 AND mo.`id_city` > 0 AND (mo.`amount` - mo.`done`) > 0
                               ORDER BY mo.`price_per_unit` DESC, mo.`id_offer` ASC LIMIT 5");
        $bStmt->execute([$offerFor]);
        $buyRows = $bStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $buyOffers = [];
        foreach ($buyRows as $r) {
            $buyOffers[] = [
                'id_deal'    => (int)$r['id_offer'],
                'resource'   => $r['res_type'],
                'amount'     => (int)$r['amount'],
                'done'       => (int)($r['done'] ?? 0),
                'unit_price' => (float)$r['price_per_unit']
            ];
        }

        // Sell offers: Asks ordered by cheapest selling price first (unit_price ASC)
        $sStmt = $db->prepare("SELECT mo.* FROM `market_offer` mo
                               INNER JOIN `city` c ON c.`id_city` = mo.`id_city` AND c.`id_player` = mo.`id_player`
                               WHERE mo.`res_type` = ? AND mo.`offer_type` = 'sell' AND mo.`status` = 'active'
                                 AND mo.`id_player` > 0 AND mo.`id_city` > 0 AND (mo.`amount` - mo.`done`) > 0
                               ORDER BY mo.`price_per_unit` ASC, mo.`id_offer` ASC LIMIT 5");
        $sStmt->execute([$offerFor]);
        $sellRows = $sStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $sellOffers = [];
        foreach ($sellRows as $r) {
            $sellOffers[] = [
                'id_deal'    => (int)$r['id_offer'],
                'resource'   => $r['res_type'],
                'amount'     => (int)$r['amount'],
                'done'       => (int)($r['done'] ?? 0),
                'unit_price' => (float)$r['price_per_unit']
            ];
        }

        sendJson([
            'buyOffers'  => $buyOffers,
            'sellOffers' => $sellOffers
        ]);
    }

    public static function getCityOffers($db) {
        $player = self::getPlayer($db);
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        requirePlayerOwnedCity($db, $player, $cityId);
        $offers = self::formatCityOffers($db, $cityId);
        sendJson($offers);
    }

    public static function proposeSellOffer($db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        requirePlayerOwnedCity($db, $player, $idCity);
        $unitPriceScaled = self::parseUnitPriceScaled($_REQUEST['unitPrice'] ?? '1');
        $resType = isset($_REQUEST['ResType']) ? trim($_REQUEST['ResType']) : 'food';
        $amount = ResourceService::parseAmount($_REQUEST['amount'] ?? '1000');

        if (!in_array($resType, ['food', 'wood', 'stone', 'metal'], true)
            || $amount === null || $amount <= 0 || $unitPriceScaled === null) {
            sendJson(['state' => 'error_invalid_input', 'msg' => 'بيانات العرض غير صحيحة']);
            return;
        }
        $unitPrice = $unitPriceScaled / 10000;
        $totalCost = self::calculateOfferValue($amount, $unitPriceScaled, true);
        if ($totalCost === null) {
            sendJson(['state' => 'error_trade_limit', 'msg' => 'قيمة العرض تتجاوز الحد المسموح']);
        }
        $fees = self::calculateFee($totalCost);
        if ($totalCost + $fees > ResourceService::MAX_CITY_BALANCE) {
            sendJson(['state' => 'error_trade_limit', 'msg' => 'قيمة العرض ورسومه تتجاوز الحد المسموح']);
        }

        $city = requirePlayerOwnedCity($db, $player, $idCity);

        if (!$city || (float)$city[$resType] < $amount) {
            sendJson(['state' => 'error_no_res', 'msg' => 'لا يمكنك بيع كمية مواد لا تملكها']);
            return;
        }

        if ((float)$city['coin'] < $fees) {
            sendJson(['state' => 'error_no_coin', 'msg' => 'لا يوجد لديك سسترسس كافى لدفع الرسوم']);
            return;
        }

        $db->beginTransaction();
        try {
            // Deduct resource and fee from selling city
            $deduct = $db->prepare("UPDATE `city` SET `{$resType}` = `{$resType}` - ?, `coin` = `coin` - ? WHERE `id_city` = ? AND `id_player` = ? AND `{$resType}` >= ? AND `coin` >= ?");
            $deduct->execute([$amount, $fees, $idCity, $playerId, $amount, $fees]);
            if ($deduct->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_no_res']);
            }

            // Trade Matching Engine: Look for active buy offers willing to pay >= unitPrice
            $remAmount = $amount;
            $totalEarnedCoins = 0;
            $buyers = [];

            $mStmt = $db->prepare("SELECT mo.* FROM `market_offer` mo
                               INNER JOIN `city` c ON c.`id_city` = mo.`id_city` AND c.`id_player` = mo.`id_player`
                               WHERE mo.`res_type` = ? AND mo.`offer_type` = 'buy' AND mo.`status` = 'active'
                                 AND mo.`price_per_unit` >= ? AND mo.`id_player` > 0 AND mo.`id_city` > 0
                                 AND mo.`id_player` != ? AND mo.`id_city` != ? AND (mo.`amount` - mo.`done`) > 0
                               ORDER BY mo.`price_per_unit` DESC, mo.`id_offer` ASC FOR UPDATE");
            $mStmt->execute([$resType, $unitPrice, $playerId, $idCity]);
            $matchable = $mStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($matchable as $bOffer) {
                if ($remAmount <= 0) break;
                $oldDone = (int)$bOffer['done'];
                $bAvail = (int)$bOffer['amount'] - $oldDone;
                $tradeAmt = min($remAmount, $bAvail);
                $tradePriceScaled = self::parseUnitPriceScaled((string)$bOffer['price_per_unit']);
                $earned = $tradePriceScaled === null
                    ? null
                    : self::calculateOfferValue($tradeAmt, $tradePriceScaled, false);
                if ($earned === null) {
                    throw new RuntimeException('Invalid price on active market offer');
                }
                if ($totalEarnedCoins > ResourceService::MAX_CITY_BALANCE - $earned) {
                    throw new ResourceLimitException('Market seller coin balance limit reached');
                }

                // Update buyer offer
                $newDone = $oldDone + $tradeAmt;
                $newStatus = ($newDone >= (int)$bOffer['amount']) ? 'completed' : 'active';
                $offerUpdate = $db->prepare("UPDATE `market_offer` SET `done` = ?, `status` = ? WHERE `id_offer` = ? AND `done` = ? AND `status` = 'active'");
                $offerUpdate->execute([$newDone, $newStatus, (int)$bOffer['id_offer'], $oldDone]);
                if ($offerUpdate->rowCount() !== 1) {
                    continue;
                }

                // Deliver resources to buyer city
                $buyerCityId = (int)$bOffer['id_city'];
                if ($buyerCityId > 0) {
                    if (!ResourceService::creditCity($db, $buyerCityId, (int)$bOffer['id_player'], [$resType => $tradeAmt])) {
                        throw new ResourceLimitException('Market buyer resource balance limit reached');
                    }
                    $buyers[] = ['id_player' => (int)$bOffer['id_player'], 'amount' => $tradeAmt];
                }

                $totalEarnedCoins += $earned;
                $remAmount -= $tradeAmt;
            }

            // Deliver earned coins from executed sales to seller city
            if ($totalEarnedCoins > 0) {
                if (!ResourceService::creditCity($db, $idCity, $playerId, ['coin' => $totalEarnedCoins])) {
                    throw new ResourceLimitException('Market seller coin balance limit reached');
                }
            }

            // If portion of offer remains unsold, post it as active sell offer
            if ($remAmount > 0) {
                $db->prepare("INSERT INTO `market_offer` (`id_player`, `id_city`, `offer_type`, `res_type`, `amount`, `price_per_unit`, `done`, `status`, `created_at`) 
                          VALUES (?, ?, 'sell', ?, ?, ?, 0, 'active', ?)")
                   ->execute([$playerId, $idCity, $resType, $remAmount, $unitPrice, time()]);
            }

            $cityStmt = $db->prepare('SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?');
            $cityStmt->execute([$idCity, $playerId]);
            $freshCity = $cityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$freshCity) {
                throw new RuntimeException('Market seller city is unavailable');
            }
            foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }

            $db->commit();
            sendJson([
                'state'      => 'ok',
                'cityRes'    => $freshCity,
                'cityOffers' => self::formatCityOffers($db, $idCity),
                'msg_num'    => 1,
                'buyers'     => $buyers
            ]);
        } catch (Throwable $error) {
            self::rejectResourceLimit($db, $error);
        }
    }

    public static function proposeBuyOffer($db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        requirePlayerOwnedCity($db, $player, $idCity);
        $unitPriceScaled = self::parseUnitPriceScaled($_REQUEST['unitPrice'] ?? '1');
        $resType = isset($_REQUEST['ResType']) ? trim($_REQUEST['ResType']) : 'food';
        $amount = ResourceService::parseAmount($_REQUEST['amount'] ?? '1000');

        if (!in_array($resType, ['food', 'wood', 'stone', 'metal'], true)
            || $amount === null || $amount <= 0 || $unitPriceScaled === null) {
            sendJson(['state' => 'error_invalid_input', 'msg' => 'بيانات العرض غير صحيحة']);
            return;
        }
        $unitPrice = $unitPriceScaled / 10000;
        $totalCost = self::calculateOfferValue($amount, $unitPriceScaled, true);
        if ($totalCost === null) {
            sendJson(['state' => 'error_trade_limit', 'msg' => 'قيمة العرض تتجاوز الحد المسموح']);
        }
        $fees = self::calculateFee($totalCost);
        if ($totalCost + $fees > ResourceService::MAX_CITY_BALANCE) {
            sendJson(['state' => 'error_trade_limit', 'msg' => 'قيمة العرض ورسومه تتجاوز الحد المسموح']);
        }

        $city = requirePlayerOwnedCity($db, $player, $idCity);

        if (!$city || (float)$city['coin'] < ($totalCost + $fees)) {
            sendJson(['state' => 'error_no_coin', 'msg' => 'عذرا ليس لديك سسترسس كافى!']);
            return;
        }

        $db->beginTransaction();
        try {
            // Deduct upfront coins and fee from buying city
            $deduct = $db->prepare("UPDATE `city` SET `coin` = `coin` - ? WHERE `id_city` = ? AND `id_player` = ? AND `coin` >= ?");
            $deduct->execute([$totalCost + $fees, $idCity, $playerId, $totalCost + $fees]);
            if ($deduct->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_no_coin']);
            }

            // Trade Matching Engine: Look for active sell offers with price <= unitPrice
            $remAmount = $amount;
            $totalResourceGained = 0;
            $totalCoinRefund = 0;
            $sellers = [];

            $mStmt = $db->prepare("SELECT mo.* FROM `market_offer` mo
                               INNER JOIN `city` c ON c.`id_city` = mo.`id_city` AND c.`id_player` = mo.`id_player`
                               WHERE mo.`res_type` = ? AND mo.`offer_type` = 'sell' AND mo.`status` = 'active'
                                 AND mo.`price_per_unit` <= ? AND mo.`id_player` > 0 AND mo.`id_city` > 0
                                 AND mo.`id_player` != ? AND mo.`id_city` != ? AND (mo.`amount` - mo.`done`) > 0
                               ORDER BY mo.`price_per_unit` ASC, mo.`id_offer` ASC FOR UPDATE");
            $mStmt->execute([$resType, $unitPrice, $playerId, $idCity]);
            $matchable = $mStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($matchable as $sOffer) {
                if ($remAmount <= 0) break;
                $oldDone = (int)$sOffer['done'];
                $sAvail = (int)$sOffer['amount'] - $oldDone;
                $tradeAmt = min($remAmount, $sAvail);
                $actualPriceScaled = self::parseUnitPriceScaled((string)$sOffer['price_per_unit']);
                $payout = $actualPriceScaled === null
                    ? null
                    : self::calculateOfferValue($tradeAmt, $actualPriceScaled, false);
                if ($payout === null) {
                    throw new RuntimeException('Invalid price on active market offer');
                }

                // Update seller offer
                $newDone = $oldDone + $tradeAmt;
                $newStatus = ($newDone >= (int)$sOffer['amount']) ? 'completed' : 'active';
                $offerUpdate = $db->prepare("UPDATE `market_offer` SET `done` = ?, `status` = ? WHERE `id_offer` = ? AND `done` = ? AND `status` = 'active'");
                $offerUpdate->execute([$newDone, $newStatus, (int)$sOffer['id_offer'], $oldDone]);
                if ($offerUpdate->rowCount() !== 1) {
                    continue;
                }

                // Pay seller city
                $sellerCityId = (int)$sOffer['id_city'];
                if ($sellerCityId > 0) {
                    if (!ResourceService::creditCity($db, $sellerCityId, (int)$sOffer['id_player'], ['coin' => $payout])) {
                        throw new ResourceLimitException('Market seller coin balance limit reached');
                    }
                    $sellers[] = ['id_player' => (int)$sOffer['id_player'], 'amount' => $tradeAmt];
                }

                // Refund difference if actual price was cheaper than buyer's maximum bid price
                if ($unitPriceScaled > $actualPriceScaled) {
                    $refund = self::calculateOfferValue(
                        $tradeAmt,
                        $unitPriceScaled - $actualPriceScaled,
                        false
                    );
                    if ($refund === null || $totalCoinRefund > ResourceService::MAX_CITY_BALANCE - $refund) {
                        throw new ResourceLimitException('Market price refund exceeds city balance limit');
                    }
                    $totalCoinRefund += $refund;
                }

                $totalResourceGained += $tradeAmt;
                $remAmount -= $tradeAmt;
            }

            // Deliver bought resources and price difference refunds to buyer city
            $buyerCredits = [];
            if ($totalResourceGained > 0) {
                $buyerCredits[$resType] = $totalResourceGained;
            }
            if ($totalCoinRefund > 0) {
                $buyerCredits['coin'] = $totalCoinRefund;
            }
            if ($buyerCredits && !ResourceService::creditCity($db, $idCity, $playerId, $buyerCredits)) {
                throw new ResourceLimitException('Market buyer balance limit reached');
            }

            // If portion of buy offer remains unfulfilled, post it as active buy tender
            if ($remAmount > 0) {
                $db->prepare("INSERT INTO `market_offer` (`id_player`, `id_city`, `offer_type`, `res_type`, `amount`, `price_per_unit`, `done`, `status`, `created_at`) 
                          VALUES (?, ?, 'buy', ?, ?, ?, 0, 'active', ?)")
                   ->execute([$playerId, $idCity, $resType, $remAmount, $unitPrice, time()]);
            }

            $cityStmt = $db->prepare('SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?');
            $cityStmt->execute([$idCity, $playerId]);
            $freshCity = $cityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$freshCity) {
                throw new RuntimeException('Market buyer city is unavailable');
            }
            foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }

            $db->commit();
            sendJson([
                'state'      => 'ok',
                'cityRes'    => $freshCity,
                'cityOffers' => self::formatCityOffers($db, $idCity),
                'msg_num'    => 1,
                'seller'     => $sellers
            ]);
        } catch (Throwable $error) {
            self::rejectResourceLimit($db, $error);
        }
    }

    public static function cancelMyOffer($db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $idOffer = isset($_REQUEST['idOffer']) ? (int)$_REQUEST['idOffer'] : 0;
        $idCity  = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        requirePlayerOwnedCity($db, $player, $idCity);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM `market_offer` WHERE `id_offer` = ? AND `id_city` = ? AND `id_player` = ? FOR UPDATE");
            $stmt->execute([$idOffer, $idCity, $playerId]);
            $offer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($offer && $offer['status'] === 'active') {
                $unfulfilled = max(0, (int)$offer['amount'] - (int)$offer['done']);
                $res = $offer['res_type'];
                if ($offer['offer_type'] === 'sell' && !in_array($res, ['food', 'wood', 'stone', 'metal'], true)) {
                    $db->rollBack();
                    sendJson(['state' => 'error_invalid_offer']);
                }

                $delete = $db->prepare("DELETE FROM `market_offer` WHERE `id_offer` = ? AND `id_city` = ? AND `id_player` = ? AND `status` = 'active'");
                $delete->execute([$idOffer, $idCity, $playerId]);
                if ($delete->rowCount() === 1 && $unfulfilled > 0) {
                    if ($offer['offer_type'] === 'sell') {
                        // Refund remaining unsold resources
                        if (!ResourceService::creditCity($db, $idCity, $playerId, [$res => $unfulfilled])) {
                            throw new ResourceLimitException('Market resource refund exceeds city balance limit');
                        }
                    } else {
                        // Refund remaining unspent coins
                        $priceScaled = self::parseUnitPriceScaled((string)$offer['price_per_unit']);
                        $coins = $priceScaled === null
                            ? null
                            : self::calculateOfferValue($unfulfilled, $priceScaled, true);
                        if ($coins === null) {
                            throw new RuntimeException('Invalid price on active market offer');
                        }
                        if (!ResourceService::creditCity($db, $idCity, $playerId, ['coin' => $coins])) {
                            throw new ResourceLimitException('Market coin refund exceeds city balance limit');
                        }
                    }
                }
            }

            $db->commit();
        } catch (Throwable $error) {
            self::rejectResourceLimit($db, $error);
        }

        $freshStmt = $db->prepare("SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?");
        $freshStmt->execute([$idCity, $playerId]);
        $freshCity = $freshStmt->fetch(PDO::FETCH_ASSOC);
        foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }

        sendJson([
            'state'      => 'ok',
            'cityRes'    => $freshCity,
            'cityOffers' => self::formatCityOffers($db, $idCity)
        ]);
    }

    public static function getCityOffersTrans($db) {
        $player = self::getPlayer($db);
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        requirePlayerOwnedCity($db, $player, $idCity);
        $now = time();
        $stmt = $db->prepare("SELECT * FROM `city_market_trans` WHERE `id_city_to` = ? AND `status` = 'marching' ORDER BY `time_arrive` ASC");
        $stmt->execute([$idCity]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $res = [];
        foreach ($rows as $r) {
            $res[] = [
                'id_deal'     => (int)$r['id_trans'],
                'resource'    => $r['resource'],
                'amount'      => (int)$r['amount'],
                'unit_price'  => (float)$r['unit_price'],
                'time_arrive' => (int)$r['time_arrive']
            ];
        }
        sendJson($res);
    }

    public static function transportResource($db) {
        $player = self::getPlayer($db);
        $idCityFrom = isset($_REQUEST['idCityFrom']) ? (int)$_REQUEST['idCityFrom'] : 0;
        $idCityTo   = isset($_REQUEST['idCityTo']) ? (int)$_REQUEST['idCityTo'] : 0;
        requirePlayerOwnedCity($db, $player, $idCityFrom);
        requirePlayerOwnedCity($db, $player, $idCityTo);
        if ($idCityFrom === $idCityTo) {
            sendJson(['state' => 'error_invalid_input']);
        }
        $food = ResourceService::parseAmount($_REQUEST['food'] ?? '0');
        $wood = ResourceService::parseAmount($_REQUEST['wood'] ?? '0');
        $stone = ResourceService::parseAmount($_REQUEST['stone'] ?? '0');
        $metal = ResourceService::parseAmount($_REQUEST['metal'] ?? '0');
        $coin = ResourceService::parseAmount($_REQUEST['coin'] ?? '0');
        if ($food === null || $wood === null || $stone === null || $metal === null || $coin === null) {
            sendJson(['state' => 'error_invalid_input']);
        }

        if ($food + $wood + $stone + $metal + $coin <= 0) {
            sendJson(['state' => 'error_invalid_input']);
        }

        $db->beginTransaction();
        try {
            $deduct = $db->prepare("UPDATE `city` SET `food` = `food` - ?, `wood` = `wood` - ?, `stone` = `stone` - ?, `metal` = `metal` - ?, `coin` = `coin` - ? WHERE `id_city` = ? AND `id_player` = ? AND `food` >= ? AND `wood` >= ? AND `stone` >= ? AND `metal` >= ? AND `coin` >= ?");
            $deduct->execute([$food, $wood, $stone, $metal, $coin, $idCityFrom, (int)$player['id_player'], $food, $wood, $stone, $metal, $coin]);
            if ($deduct->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_not_enough_resources']);
            }

            if (!ResourceService::creditCity($db, $idCityTo, (int)$player['id_player'], [
                'food' => $food,
                'wood' => $wood,
                'stone' => $stone,
                'metal' => $metal,
                'coin' => $coin
            ])) {
                $db->rollBack();
                sendJson(['state' => 'error_resource_limit', 'msg' => 'سيتجاوز النقل الحد الأقصى لموارد المدينة المستقبلة']);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        $freshStmt = $db->prepare("SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?");
        $freshStmt->execute([$idCityFrom, (int)$player['id_player']]);
        $freshCity = $freshStmt->fetch(PDO::FETCH_ASSOC);
        foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }

        sendJson([
            'state'   => 'ok',
            'City'    => $freshCity,
            'cityRes' => $freshCity
        ]);
    }

    public static function getCityTransportResource($db) {
        $player = self::getPlayer($db);
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        requirePlayerOwnedCity($db, $player, $idCity);
        $stmt = $db->prepare("SELECT * FROM `city_market_trans` WHERE (`id_city_from` = ? OR `id_city_to` = ?) AND `status` = 'marching'");
        $stmt->execute([$idCity, $idCity]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        sendJson(['state' => 'ok', 'transports' => $rows]);
    }

    public static function speedUpTransport($db) {
        self::getPlayer($db);
        http_response_code(501);
        sendJson(['state' => 'error_not_implemented']);
    }
}
