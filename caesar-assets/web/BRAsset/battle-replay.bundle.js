/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "../Client/Base/Net/XNet.Rest.ts"
/*!***************************************!*\
  !*** ../Client/Base/Net/XNet.Rest.ts ***!
  \***************************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.XNetRest = void 0;
const XLog_1 = __webpack_require__(/*! ../../../Common/XLog */ "../Common/XLog.ts");
const XToken_1 = __webpack_require__(/*! ../XToken */ "../Client/Base/XToken.ts");
class XNetRest {
    static async Get(url, params) {
        try {
            const lResponse = await fetch(url + (params ? `?${new URLSearchParams(params)}` : ""), {
                method: "GET",
                headers: {
                    "Content-Type": "application/json",
                    Authorization: `Bearer ${await XToken_1.XToken.GetToken()}`,
                },
            });
            const lData = (await lResponse.json());
            return lData;
        }
        catch (e) {
            XLog_1.XLog.Error(`Error On Get Request ${url}`, e);
            throw e;
        }
    }
    static async Post(url, body) {
        try {
            const lResponse = await fetch(url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Authorization: `Bearer ${await XToken_1.XToken.GetToken()}`,
                },
                body: JSON.stringify(body),
            });
            const lData = (await lResponse.json());
            return lData;
        }
        catch (e) {
            XLog_1.XLog.Error(`Error On Post Request ${url} with body ${JSON.stringify(body)}`, e);
            throw e;
        }
    }
}
exports.XNetRest = XNetRest;


/***/ },

/***/ "../Client/Base/Translate/XTranslate.ts"
/*!**********************************************!*\
  !*** ../Client/Base/Translate/XTranslate.ts ***!
  \**********************************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.XTranslate = void 0;
const EnLan = "en";
const ArLang = "ar";
const EsLang = "es";
const PtLang = "pt";
const DeLang = "de";
const CnLang = "zh";
const DefaultLang = EnLan;
class XTranslate {
    static async Init() {
        const lJson = await fetch("/Json/MultiLang/langAr.json");
        XTranslate._LangJson = await lJson.json();
    }
    static i18n(key, params = {}) {
        if (!key) {
            console.error("i18n key is undefined");
            return "";
        }
        const template = XTranslate._LangJson[key] || key;
        return template.replace(/{(.*?)}/g, (_, p1) => {
            return params[p1] !== undefined ? String(params[p1]) : `{${p1}}`;
        });
    }
    static Translate(text) {
        // TODO: Use your own API key
        // return $.ajax({
        //   url: "https://translation.googleapis.com/language/translate/v2",
        //   type: 'GET',
        //   data: {
        //     q: text,
        //     target: UserLag.language,
        //     key: "AIzaSyD8OF2mZYMNT2o4ASG6eRUFZGX_ecQLIZA"
        //   },
        //   beforeSend: function (xhr) {
        //   },
        //   success: function (data, textStatus, jqXHR) {
        //   },
        //   error: function (jqXHR, textStatus, errorThrown) {
        //   }
        // });
    }
    static TranslateChatMsg(msg) {
        // TODO
        // if ((/\[\s*\d{1,3}\,\s*\d{1,3}\s*\]/g).test(msg.text))
        //   return;
        // if (UserLag.language === ArLang && (/[\u0600-\u06FF\u0750-\u077F]/g).test(msg.text)) {
        // } else {
        //   UserLag.Translte(msg.text).done(function (data) {
        //     if (data.data && data.data.translations[0]) {
        //       $("#mg-id-" + msg.id + " .msg-text").html(Extract.coords(extractEmjoi(extractUrl(data.data.translations[0].translatedText))) + `<i class="translated trans">مترجم</i>`);
        //       $("#mg-id-" + msg.id + " .msg-text").attr("data-msg-translated", data.data.translations[0].translatedText);
        //     }
        //   });
        // }
    }
}
exports.XTranslate = XTranslate;
XTranslate._LangJson = {};
XTranslate.LANG_DIC = {
    ar: /[\u0600-\u06FF]/,
};
$(document).on("click", "#msg-area .msg-body .trans", function () {
    var orgMsg = $(this).parent(".msg-text").attr("data-msg-org");
    $(this)
        .parent(".msg-text")
        .html(orgMsg + `<i class="translated antiTrans">ترجم</i>`);
});
$(document).on("click", "#msg-area .msg-body .antiTrans", function () {
    var orgMsg = $(this).parent(".msg-text").attr("data-msg-translated");
    $(this)
        .parent(".msg-text")
        .html(orgMsg + `<i class="translated trans">مترجم</i>`);
});
$(document).on("PlayerReady", function () {
    // if (Elkaisar.DPlayer.Player.lang) {
    //   UserLag.language = Elkaisar.DPlayer.Player.lang;
    // } else {
    //   var lang = (navigator.language || navigator.userLanguage).slice(0, 2);
    //   if (lang === ArLang) {
    //     UserLag.language = ArLang;
    //   } else if (lang === EsLang) {
    //     UserLag.language = EsLang;
    //   } else if (lang === PtLang) {
    //     UserLag.language = PtLang;
    //   } else if (lang === DeLang) {
    //     UserLag.language = DeLang;
    //   } else if (lang === CnLang) {
    //     UserLag.language = CnLang;
    //   } else {
    //     UserLag.language = EnLan;
    //   }
    //   UserLag.isDefault = true;
    // }
    // Elkaisar.MenuList.menu();
});


/***/ },

/***/ "../Client/Base/XToken.ts"
/*!********************************!*\
  !*** ../Client/Base/XToken.ts ***!
  \********************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.XToken = void 0;
class XToken {
    static async SetToken(token) {
        try {
            await cookieStore.set("token", token);
        }
        catch (e) {
            alert("Cannot set Token");
        }
        localStorage.setItem("token", token);
    }
    static async GetToken() {
        const lToken = await cookieStore.get("token");
        return lToken?.value || localStorage.getItem("token");
    }
    static async DeleteToken() {
        await cookieStore.delete("token");
        await cookieStore.delete("last_server");
        localStorage.removeItem("token");
        localStorage.removeItem("last_server");
    }
    static async GetLastServer() {
        const lastServer = await cookieStore.get("last_server");
        return (Number(lastServer?.value) ??
            Number(localStorage.getItem("last_server")) ??
            2);
    }
    static async SetLastServer(serverID) {
        await cookieStore.set("last_server", serverID.toString());
        localStorage.setItem("last_server", serverID.toString());
    }
}
exports.XToken = XToken;


/***/ },

/***/ "../Client/Core/Engine/XEnum.ts"
/*!**************************************!*\
  !*** ../Client/Core/Engine/XEnum.ts ***!
  \**************************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.EResType = void 0;
var EResType;
(function (EResType) {
    EResType[EResType["None"] = 0] = "None";
    EResType[EResType["Texture"] = 1] = "Texture";
    EResType[EResType["SpriteFrame"] = 2] = "SpriteFrame";
    EResType[EResType["Animation"] = 3] = "Animation";
    EResType[EResType["Prefab"] = 4] = "Prefab";
    EResType[EResType["Audio"] = 5] = "Audio";
    EResType[EResType["Font"] = 6] = "Font";
    EResType[EResType["Json"] = 7] = "Json";
    EResType[EResType["Text"] = 8] = "Text";
    EResType[EResType["Shader"] = 9] = "Shader";
    EResType[EResType["Material"] = 10] = "Material";
})(EResType || (exports.EResType = EResType = {}));


/***/ },

/***/ "../Client/Core/Engine/XLoader.ts"
/*!****************************************!*\
  !*** ../Client/Core/Engine/XLoader.ts ***!
  \****************************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.XLoader = void 0;
const XLog_1 = __webpack_require__(/*! ../../../Common/XLog */ "../Common/XLog.ts");
const XEnum_1 = __webpack_require__(/*! ./XEnum */ "../Client/Core/Engine/XEnum.ts");
cc.loader.register(["skel"], cc._binaryLoader);
class XLoader {
    constructor() { }
    static Get() {
        if (!XLoader._Inst)
            XLoader._Inst = new XLoader();
        return XLoader._Inst;
    }
    static get Inst() {
        return XLoader.Get();
    }
    LoadList(resList, loadingCallback) {
        return new Promise((resolve, reject) => {
            this._ResList = resList;
            this._loadedResCount = 0;
            this._loadingResCount = 0;
            this._totalResCount = resList.length;
            this._loadedResList = [];
            this._loadingResList = [];
            for (let i = 0; i < resList.length; i++) {
                const res = resList[i];
                if (res.Type == XEnum_1.EResType.SpriteFrame) {
                    this.LoadSpriteSheet(res.url)
                        .then(() => {
                        this._loadedResCount++;
                        this._loadedResList.push(res);
                        loadingCallback?.(res);
                        if (this._loadedResCount == this._totalResCount)
                            resolve();
                    })
                        .catch((err) => {
                        XLog_1.XLog.Error(err);
                    });
                    continue;
                }
                this.Load(res.url)
                    .then(() => {
                    this._loadedResCount++;
                    this._loadedResList.push(res);
                    loadingCallback?.(res);
                    if (this._loadedResCount == this._totalResCount)
                        resolve();
                })
                    .catch((err) => {
                    XLog_1.XLog.Error(err);
                });
            }
        });
    }
    Load(url) {
        return new Promise(function (resolve, reject) {
            cc.loader.load(url, function () {
                if (arguments[0]) {
                    reject(arguments[0]);
                }
                resolve(arguments[1]);
            });
        });
    }
    LoadParticle(url) {
        return new Promise(function (resolve, reject) {
            cc.loader.load(url, function () {
                if (arguments[0]) {
                    reject(arguments[0]);
                }
                const lPart = arguments[1];
                // Issue
                // lPart.retain();
                resolve(lPart);
            });
        });
    }
    LoadBinary(url) {
        return new Promise((resolve, reject) => {
            cc.loader.loadBinary(url, function () {
                resolve(arguments[1]);
            });
        });
    }
    async LoadSpriteSheet(url) {
        const lPlist = await this.Load(url);
        cc.spriteFrameCache.addSpriteFrames(url);
    }
    LoadCCSNode(url) {
        return new Promise((resolve, reject) => {
            this.Load(url).then((contentJson) => {
                let lJson;
                if (Array.isArray(contentJson))
                    lJson = contentJson[0];
                else
                    lJson = contentJson;
                try {
                    let lNode = ccs.load(url, "");
                    // lNode.node.retain();
                    // lNode.action.retain();
                    cc.log(`load UI json File: ${url}`);
                    Promise.all(lJson.Content.Content.UsedResourcesNew?.map((element) => this.Load(element)) || [])
                        .then((resources) => {
                        resolve(lNode);
                    })
                        .catch((error) => {
                        cc.error(`Error loading resources: ${error}`);
                        reject();
                    });
                }
                catch (e) {
                    cc.error(`Error loading CCS node: ${e}`);
                    reject();
                }
            });
        });
    }
}
exports.XLoader = XLoader;
window.XLoader = XLoader;


/***/ },

/***/ "../Client/Module/Player/Item.Static.ts"
/*!**********************************************!*\
  !*** ../Client/Module/Player/Item.Static.ts ***!
  \**********************************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.ItemStatic = void 0;
const Item_Config_1 = __webpack_require__(/*! ../../../Common/Player/Config/Item.Config */ "../Common/Player/Config/Item.Config.ts");
const XLog_1 = __webpack_require__(/*! ../../../Common/XLog */ "../Common/XLog.ts");
const XTranslate_1 = __webpack_require__(/*! ../../Base/Translate/XTranslate */ "../Client/Base/Translate/XTranslate.ts");
class ItemStatic {
    static getItemName(itemId) {
        if (!Item_Config_1.CItemConfig[itemId]?.name) {
            XLog_1.XLog.Error(`ItemStatic.getItemName: Item Name Not Found for Item ID ${itemId}`);
        }
        return XTranslate_1.XTranslate.i18n(Item_Config_1.CItemConfig[itemId]?.name ?? "UNKNOWN_ITEM");
    }
    static image(itemId) {
        if (!Item_Config_1.CItemConfig[itemId]?.image) {
            XLog_1.XLog.Error(`ItemStatic.image: Item Image Not Found for Item ID ${itemId}`);
        }
        return Item_Config_1.CItemConfig[itemId]?.image ?? "/path/to/default/image.png";
    }
}
exports.ItemStatic = ItemStatic;


/***/ },

/***/ "../Common/Battle/Battle.Enum.ts"
/*!***************************************!*\
  !*** ../Common/Battle/Battle.Enum.ts ***!
  \***************************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.ESoldierStateInBattle = exports.EAttackHitSkillType = exports.EAttackHitType = exports.EBattleFinishState = exports.EBattleSeen = exports.EBattleSide = exports.EBattleTask = void 0;
var EBattleTask;
(function (EBattleTask) {
    EBattleTask[EBattleTask["OPEN_BATTEL_FIELD"] = -1] = "OPEN_BATTEL_FIELD";
    EBattleTask[EBattleTask["CONQUER"] = 0] = "CONQUER";
    EBattleTask[EBattleTask["DOMINATE"] = 1] = "DOMINATE";
    EBattleTask[EBattleTask["JOIN_ATT"] = 2] = "JOIN_ATT";
    EBattleTask[EBattleTask["JOIN_DEF"] = 3] = "JOIN_DEF";
    EBattleTask[EBattleTask["SPY"] = 4] = "SPY";
    EBattleTask[EBattleTask["SUPPORT"] = 5] = "SUPPORT";
    EBattleTask[EBattleTask["HERO_TRANS"] = 6] = "HERO_TRANS";
    EBattleTask[EBattleTask["SUPPLY"] = 7] = "SUPPLY";
    EBattleTask[EBattleTask["ENTER_CITY"] = 8] = "ENTER_CITY";
    EBattleTask[EBattleTask["CHALLANGE"] = 10] = "CHALLANGE";
})(EBattleTask || (exports.EBattleTask = EBattleTask = {}));
var EBattleSide;
(function (EBattleSide) {
    EBattleSide[EBattleSide["Defense"] = 0] = "Defense";
    EBattleSide[EBattleSide["Attack"] = 1] = "Attack";
})(EBattleSide || (exports.EBattleSide = EBattleSide = {}));
var EBattleSeen;
(function (EBattleSeen) {
    EBattleSeen[EBattleSeen["Unseen"] = 0] = "Unseen";
    EBattleSeen[EBattleSeen["Seen"] = 1] = "Seen";
})(EBattleSeen || (exports.EBattleSeen = EBattleSeen = {}));
var EBattleFinishState;
(function (EBattleFinishState) {
    EBattleFinishState[EBattleFinishState["loser"] = 0] = "loser";
    EBattleFinishState[EBattleFinishState["winner"] = 1] = "winner";
})(EBattleFinishState || (exports.EBattleFinishState = EBattleFinishState = {}));
var EAttackHitType;
(function (EAttackHitType) {
    EAttackHitType[EAttackHitType["Norm"] = 0] = "Norm";
    EAttackHitType[EAttackHitType["Break"] = 1] = "Break";
    EAttackHitType[EAttackHitType["Strike"] = 2] = "Strike";
    EAttackHitType[EAttackHitType["StrikeAndBreak"] = 3] = "StrikeAndBreak";
})(EAttackHitType || (exports.EAttackHitType = EAttackHitType = {}));
// tf.archers.skill.desc=Weakly armored infantry transferable to the distal division IV, V or VI in the rear to launch ranged attack with bow. Inept at fighting Principes with 70% of the regular damage dealt. Capable of close combat but damage is halved.
// tf.ballistas.skill.desc=Siege engine shaped like an enormous crossbow with incredible range and penetration power transferable to the distal division IV, V or VI in the rear to launch ranged attack. Especially lethal to Barbican with 150% damage boost. Capable of close combat but damage is halved.
// tf.cavalry.skill.desc=Highly mobilized horsemen transferable to the proximal division I, II or III in the front to launch close combat. Especially lethal to Sagittarii and Principes with 120% damage boost. Cannot attack Wall.
// tf.onagers.skill.desc=Versatile catapult siege engine transferable to the distal division IV, V or VI in the rear to launch ranged attack. Especially lethal to Wall and Barbican with 200% damage boost. Capable of close combat but damage is halved.
// tf.scout.skill.desc=Special task unit that cannot be assigned to a hero. Dispatched separately to gather intelligence on wilderness locations or other cities. Speculatores only engage in combat with other Speculatores.
// tf.triarii.skill.desc=Lighter infantry armed with spears and shields transferable to the proximal division I, II or III in the front to launch close combat. Especially lethal to Equites with 180% damage boost.
// tf.urban_cohort.skill.desc=Elite infantry transferable to the proximal division I, II or III in the front to launch close combat. Especially lethal to Hastati with 110% damage boost. Uniquely gifted with Missile technique capable of launching auxiliary attack to a division. resistrush.desc=A Hastatus can disarm Equites' Ambush Assault, effectively protecting distal division in the rear. scattering.desc=Attaining Footpace Lv 7, Sagittarii get Dispersion, enable them to attack all enemy units at once but causing only a quarter of the usual damage as when it's hitting just one particular unit. (Dispersion is only used against divisions if the damage to their HP is greater than Sagittarii' regular attack.) rush.desc=With Equitation Lv 7, Equites get Ambush Assault, which power becomes effectual instantly. It adds collateral damage to the rear division with magnitude that's half of the front when Equites attack a forefront division (not diagonal). However, a rear division suffers no collateral damage from Ambush Assault if Hastati is the forefront division. Flank assault doesn't invoke Ambush Assault. sweeparmy.desc=Annihilation Sweep comes in handy at the end of a forefront attack. After destroying the forefront enemy division, without having to wait for another round, Annihilation Sweep enables Equites to attack the remaining divisions of the enemy hero. The all out attack towards the rest of the divisions is done without deduction in damage to individual division. sideinjure.desc=When the front line enemy division standing in front of the Equites is empty or fallen, Equites' Flank Assail boosts the damage by 50% with Flank Expedience. perforation.desc=Upon attaining Arming Lv 7, Piercing is enabled on Ballistae which add collateral damage to a rear division in the same column. This added damage is equal to half of what the front division would take, and will not take effect diagonally. tozt.desc=Upon attaining Arming Lv 7, Onagers get Fire Balls which double their damaging power. Additionally they bring collateral damage to Barbicans when attacking the Wall. The collateral damage done to Barbicans is half of that done to the Wall. longrangestrike.desc=Principes are uniquely gifted with Missile technique that enables them to launch auxiliary attack (in the first 2 rounds only). Missile automatically selects optimal division (both distal and proximal ones) to attack to maximize the theoretical damage (but actual damage may differ due to damage curb of individual troop's unit). Missile's auxiliary damage is half of that of Principes' close combat.
var EAttackHitSkillType;
(function (EAttackHitSkillType) {
    EAttackHitSkillType[EAttackHitSkillType["None"] = 0] = "None";
    EAttackHitSkillType[EAttackHitSkillType["Normal"] = 1] = "Normal";
    /*Sagittarius*/
    EAttackHitSkillType[EAttackHitSkillType["Archer_Normal"] = 2] = "Archer_Normal";
    EAttackHitSkillType[EAttackHitSkillType["Archer_Dispersion"] = 3] = "Archer_Dispersion";
    EAttackHitSkillType[EAttackHitSkillType["Archer_RainOfArrows"] = 4] = "Archer_RainOfArrows";
    EAttackHitSkillType[EAttackHitSkillType["Archer_RainOfArrowsBack"] = 5] = "Archer_RainOfArrowsBack";
    /**Equites */
    EAttackHitSkillType[EAttackHitSkillType["Cavalry_Normal"] = 6] = "Cavalry_Normal";
    //❗ Cancelled if enemy front = Hastatus
    // 100% Damage to the front, 50% to the rear (at the same column)
    EAttackHitSkillType[EAttackHitSkillType["Cavalry_AmbushAssault"] = 7] = "Cavalry_AmbushAssault";
    // 150% Damage to the rear (at the same column) when the front is empty or fallen
    EAttackHitSkillType[EAttackHitSkillType["Cavalry_FlankAssault"] = 8] = "Cavalry_FlankAssault";
    // After destroying the front enemy division, attack the rest of the divisions of the enemy hero with no deduction in damage to individual division. Only effective when attacking a forefront division (not diagonal).
    EAttackHitSkillType[EAttackHitSkillType["Cavalry_AnnihilationSweep"] = 9] = "Cavalry_AnnihilationSweep";
    /**Hastatus */
    EAttackHitSkillType[EAttackHitSkillType["Hastatus_Normal"] = 10] = "Hastatus_Normal";
    // 180% Damage to Equites when attacking a forefront division (not diagonal).
    EAttackHitSkillType[EAttackHitSkillType["Hastatus_Piercing"] = 11] = "Hastatus_Piercing";
    // 50% damage for the best Target Division (first 2  rounds only)
    EAttackHitSkillType[EAttackHitSkillType["Hastatus_Missile"] = 12] = "Hastatus_Missile";
    EAttackHitSkillType[EAttackHitSkillType["Principes_Normal"] = 13] = "Principes_Normal";
    // 50% damage for the best Target Division
    EAttackHitSkillType[EAttackHitSkillType["Principes_Missile"] = 14] = "Principes_Missile";
    /**Ballistae */
    // 50% collateral to rear division in same column when attacking a front division
    EAttackHitSkillType[EAttackHitSkillType["Ballistae_Perforation"] = 15] = "Ballistae_Perforation";
    /**Onager */
    // 50% collateral to Barbican (wall_b) when attacking Wall (wall_a/wall_c)
    EAttackHitSkillType[EAttackHitSkillType["Onager_FireBalls"] = 16] = "Onager_FireBalls";
})(EAttackHitSkillType || (exports.EAttackHitSkillType = EAttackHitSkillType = {}));
var ESoldierStateInBattle;
(function (ESoldierStateInBattle) {
    ESoldierStateInBattle["Idle"] = "Idle";
    ESoldierStateInBattle["Attack"] = "Attack";
})(ESoldierStateInBattle || (exports.ESoldierStateInBattle = ESoldierStateInBattle = {}));


/***/ },

/***/ "../Common/Battle/Config/BattleTask.Config.ts"
/*!****************************************************!*\
  !*** ../Common/Battle/Config/BattleTask.Config.ts ***!
  \****************************************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.CBattleTaskConfig = void 0;
const Battle_Enum_1 = __webpack_require__(/*! ../Battle.Enum */ "../Common/Battle/Battle.Enum.ts");
exports.CBattleTaskConfig = {
    [Battle_Enum_1.EBattleTask.CONQUER]: {
        title: "military.mission.loot",
    },
    [Battle_Enum_1.EBattleTask.DOMINATE]: {
        title: "military.mission.attack",
    },
    [Battle_Enum_1.EBattleTask.JOIN_ATT]: {
        title: "انضمام للهجوم ",
    },
    [Battle_Enum_1.EBattleTask.JOIN_DEF]: {
        title: "انضمام للدفاع",
    },
    [Battle_Enum_1.EBattleTask.SPY]: {
        title: "military.mission.scout",
    },
    [Battle_Enum_1.EBattleTask.SUPPORT]: {
        title: "military.mission.reinforce",
    },
    [Battle_Enum_1.EBattleTask.HERO_TRANS]: {
        title: "military.mission.move",
    },
    [Battle_Enum_1.EBattleTask.CHALLANGE]: {
        title: "newArena.team.challange",
    },
};


/***/ },

/***/ "../Common/Enum/Army.Config.ts"
/*!*************************************!*\
  !*** ../Common/Enum/Army.Config.ts ***!
  \*************************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.CArmyConfig = void 0;
const Army_Enum_1 = __webpack_require__(/*! ./Army.Enum */ "../Common/Enum/Army.Enum.ts");
const Building_Enum_1 = __webpack_require__(/*! ./Building.Enum */ "../Common/Enum/Building.Enum.ts");
const Common_Enum_1 = __webpack_require__(/*! ./Common.Enum */ "../Common/Enum/Common.Enum.ts");
const Study_Enum_1 = __webpack_require__(/*! ./Study.Enum */ "../Common/Enum/Study.Enum.ts");
/**
 * @Original CArmyConfig
 */
exports.CArmyConfig = {
    [Army_Enum_1.EArmyType.NONE]: {
        Req: {
            food: 0,
            wood: 0,
            stone: 0,
            metal: 0,
            coin: 0,
            people: 0,
            time: 0,
        },
        condition: [],
        Title: "جواسيس",
        image: "Res/tech/no_army.png",
        Desc: `الفرسا اسرع الفرسان مش عارف اى 
                                    ليس على كل لاعب الالتذام بذالك `,
        Power: {
            vit: 0,
            attack: 0,
            defence: 0,
            damage: {
                min: 0,
                max: 0,
            },
            break: 0,
            anti_break: 0,
            strike: 0,
            immunity: 0,
            eating: 0,
            speed: 10,
            capacity: 0,
        },
        dismess: {
            food: 0,
            wood: 0,
            stone: 0,
            metal: 0,
        },
        gainedPrestige: 0,
        Icon: "no_army",
    },
    [Army_Enum_1.EArmyType.spies]: {
        // جواسيس
        Req: {
            food: 600,
            wood: 150,
            stone: 0,
            metal: 350,
            coin: 90,
            people: 1,
            time: 60,
        },
        condition: [
            {
                type: Common_Enum_1.ECondType.Building,
                lvl: 1,
                Type: Building_Enum_1.EBuildingType.STABL,
            },
            { type: Common_Enum_1.ECondType.Study, lvl: 1, Type: Study_Enum_1.EStudyType.riding },
        ],
        Title: "جواسيس",
        image: "Res/items/item027.jpg",
        Desc: `كانت الجواسيس هى سلاح الاستخبارت فى العصور الوسطى و كانت اهميتها تكمن فى جب الاخبار من المدن الاخرى 
            ولكن لا يمكنك الوثوق بهذه المعلومات بسسب قلة كفائة الجواسيس لديك`,
        Power: {
            vit: 80,
            attack: 10,
            defence: 12,
            damage: { min: 6, max: 9 },
            break: 0,
            anti_break: 0,
            strike: 0,
            immunity: 0,
            eating: 6,
            speed: 2000,
            capacity: 1,
        },
        dismess: {
            food: 180,
            wood: 45,
            stone: 0,
            metal: 105,
        },
        gainedPrestige: 3,
        Icon: "Res/tech/spies.png",
    },
    [Army_Enum_1.EArmyType.army_a]: {
        // مشاه
        Req: {
            food: 150,
            wood: 500,
            stone: 0,
            metal: 100,
            coin: 18,
            people: 1,
            time: 50,
        },
        condition: [
            {
                type: Common_Enum_1.ECondType.Building,
                lvl: 1,
                Type: Building_Enum_1.EBuildingType.BARRACKS,
            },
            { type: Common_Enum_1.ECondType.Study, lvl: 1, Type: Study_Enum_1.EStudyType.infantry },
        ],
        Title: "مشاه",
        image: "Res/tech/soldier01.jpg",
        Desc: `اكثر انواع الجيوش استعمالا اثناء الامبراطورية الرومانية 
                وذلك بسسب سهولة تدريبها  وتسليحها وتكمن قوتها  فى  الاسرار الحربية لديها`,
        Power: {
            vit: 60,
            attack: 8,
            defence: 8,
            damage: { min: 3, max: 6 },
            break: 1,
            anti_break: 1,
            strike: 3,
            immunity: 2,
            eating: 4,
            speed: 300,
            capacity: 40,
        },
        dismess: {
            food: 45,
            wood: 150,
            stone: 0,
            metal: 30,
        },
        gainedPrestige: 1.5,
        Icon: "Res/tech/soldier_1.jpg",
    },
    [Army_Enum_1.EArmyType.army_b]: {
        // اسطبل
        Req: {
            food: 1500,
            wood: 800,
            stone: 0,
            metal: 750,
            coin: 500,
            people: 3,
            time: 300,
        },
        condition: [
            {
                type: Common_Enum_1.ECondType.Building,
                lvl: 5,
                Type: Building_Enum_1.EBuildingType.STABL,
            },
            { type: Common_Enum_1.ECondType.Study, lvl: 3, Type: Study_Enum_1.EStudyType.riding },
        ],
        Title: "فرسان",
        image: "Res/tech/soldier02.jpg",
        Desc: `سلاح الفرسان هو نموذج الفارس الممتاز في الجيش الروماني من حيث تسليحه عالي المستوى. يخيف هجوم سلاح الفرسان الخصم غير المستعدّ على الرغم من أنهم ليسوا أسرع القوات. مشكلتهم تكلفة صيانتهم لما يتوجب على من يدرّبهم من رصدٍ لطعام الفارس وفرسه.`,
        Power: {
            vit: 250,
            attack: 30,
            defence: 20,
            damage: { min: 33, max: 38 },
            break: 10,
            anti_break: 4,
            strike: 1,
            immunity: 2,
            eating: 18,
            speed: 900,
            capacity: 100,
        },
        dismess: {
            food: 450,
            wood: 240,
            stone: 0,
            metal: 225,
        },
        Icon: "Res/tech/soldier_2.jpg",
        gainedPrestige: 3,
    },
    [Army_Enum_1.EArmyType.army_c]: {
        // مدرعين
        Req: {
            food: 2000,
            wood: 500,
            stone: 0,
            metal: 2500,
            coin: 600,
            people: 6,
            time: 500,
        },
        condition: [
            {
                type: Common_Enum_1.ECondType.Building,
                lvl: 9,
                Type: Building_Enum_1.EBuildingType.BARRACKS,
            },
            { type: Common_Enum_1.ECondType.Study, lvl: 6, Type: Study_Enum_1.EStudyType.infantry },
        ],
        Title: "مدرعين",
        image: "Res/tech/soldier03.jpg",
        Desc: `المدرعين هم اساس القوات الرومانية بتسليحهم المعقد وتدريباتهم المكثفة يمكن الاعتبار انها من اقوى انواع الجيوش
            يستغرق تدريب الابطال كمية كبيرة كدا من الوقت وذلك بسبب الالتزام لصنع اقوى الابطال`,
        Power: {
            vit: 400,
            attack: 25,
            defence: 30,
            damage: { min: 40, max: 60 },
            break: 10,
            anti_break: 5,
            strike: 10,
            immunity: 5,
            eating: 36,
            speed: 600,
            capacity: 120,
        },
        dismess: {
            food: 600,
            wood: 150,
            stone: 0,
            metal: 750,
        },
        gainedPrestige: 19,
        Icon: "Res/tech/soldier_3.jpg",
    },
    [Army_Enum_1.EArmyType.army_d]: {
        // رماه
        Req: {
            food: 300,
            wood: 350,
            stone: 0,
            metal: 300,
            coin: 30,
            people: 1,
            time: 120,
        },
        condition: [
            {
                type: Common_Enum_1.ECondType.Building,
                lvl: 2,
                Type: Building_Enum_1.EBuildingType.BARRACKS,
            },
            { type: Common_Enum_1.ECondType.Study, lvl: 2, Type: Study_Enum_1.EStudyType.infantry },
        ],
        Title: "رماه",
        image: "Res/tech/soldier04.jpg",
        Desc: `رماة السهم او  النبالين كانوا زمرة الجيش الرومانى.
                يمكنك الاعتماد عليهم فى الهجوم اما  بالنسبة الى الى الدفاع فلا يمكن ابدا الاعتماد عليهم.
                بسبب ضعف البنية الجسمانية لديهم ولكن يمكن لهذة القوات تنفيذ العديد من الاصابات البالغة  للاعداء`,
        Power: {
            vit: 45,
            attack: 9,
            defence: 5,
            damage: { min: 3, max: 5 },
            break: 2,
            anti_break: 2,
            strike: 3,
            immunity: 2,
            eating: 5,
            speed: 250,
            capacity: 25,
        },
        dismess: {
            food: 90,
            wood: 105,
            stone: 0,
            metal: 90,
        },
        gainedPrestige: 2,
        Icon: "Res/tech/soldier_4.jpg",
    },
    [Army_Enum_1.EArmyType.army_e]: {
        // مقاليع
        Req: {
            food: 1000,
            wood: 1200,
            stone: 0,
            metal: 800,
            coin: 120,
            people: 4,
            time: 180,
        },
        condition: [
            {
                type: Common_Enum_1.ECondType.Building,
                lvl: 3,
                Type: Building_Enum_1.EBuildingType.WORKSHOP,
            },
            { type: Common_Enum_1.ECondType.Study, lvl: 1, Type: Study_Enum_1.EStudyType.army },
        ],
        Title: "مقاليع",
        image: "Res/tech/soldier05.jpg",
        Desc: `كانت العصور الرومانية عصور ازدهار هندسى ومعمارى .
                احد الأدلة على ذلك هو  سلاح المقاليع لدى الجيوش الرمانية .
                يلحق هذا النوع ضرر كبير جدا  بالاعداء  مهما كانت قوتهم ويشتت جمعهم`,
        Power: {
            vit: 100,
            attack: 19,
            defence: 25,
            damage: { min: 18, max: 20 },
            break: 5,
            anti_break: 2,
            strike: 15,
            immunity: 8,
            eating: 20,
            speed: 150,
            capacity: 35,
        },
        dismess: {
            food: 300,
            wood: 360,
            stone: 0,
            metal: 240,
        },
        gainedPrestige: 8,
        Icon: "Res/tech/soldier_5.jpg",
    },
    [Army_Enum_1.EArmyType.army_f]: {
        // منجنيق
        Req: {
            food: 3000,
            wood: 3000,
            stone: 6000,
            metal: 1200,
            coin: 450,
            time: 1000,
            people: 8,
        },
        condition: [
            {
                type: Common_Enum_1.ECondType.Building,
                lvl: 7,
                Type: Building_Enum_1.EBuildingType.WORKSHOP,
            },
            { type: Common_Enum_1.ECondType.Study, lvl: 6, Type: Study_Enum_1.EStudyType.army },
        ],
        Title: "منجنيق",
        image: "Res/tech/soldier06.jpg",
        Desc: `اقوى  انوع القوات على الاطلاق .
                لا شك فى ذلك حيث ان هذه القوات مسؤلة عن هدم الاسوار والمبانى الشاهقة.
                فليس من الصعب عليها سحق  الاعداء`,
        Power: {
            vit: 600,
            attack: 40,
            defence: 20,
            damage: { min: 70, max: 70 },
            break: 15,
            anti_break: 4,
            strike: 5,
            immunity: 15,
            eating: 150,
            speed: 100,
            capacity: 75,
        },
        dismess: {
            food: 900,
            wood: 900,
            stone: 1800,
            metal: 360,
        },
        gainedPrestige: 38,
        Icon: "Res/tech/soldier_6.jpg",
    },
    [Army_Enum_1.EArmyType.wall_a]: {
        //كمائن
        Req: {
            food: 50,
            wood: 500,
            stone: 100,
            metal: 50,
            coin: 0,
            time: 60,
            people: 0,
        },
        condition: [
            {
                type: Common_Enum_1.ECondType.Building,
                lvl: 1,
                Type: Building_Enum_1.EBuildingType.WALL,
            },
            { type: Common_Enum_1.ECondType.Study, lvl: 1, Type: Study_Enum_1.EStudyType.safe },
        ],
        Title: "كمائن",
        image: "Res/tech/defense01.jpg",
        Desc: `يتم دس الكمائن داخ السور لعرقلة الاعداء.
                ولكن لا يمكن الاعتماد عليها  فى صد الهجمات`,
        Power: {
            attack: 20,
            defence: 10,
            vit: 300,
            damage: { min: 10, max: 10 },
            break: 5,
            anti_break: 4,
            strike: 15,
            immunity: 5,
            capacity: 0,
            speed: 0,
            eating: 0,
        },
        dismess: {
            food: 15,
            wood: 150,
            stone: 30,
            metal: 15,
        },
        wall_space: 1,
        gainedPrestige: 1.5,
        Icon: "Res/tech/defense01.jpg",
    },
    [Army_Enum_1.EArmyType.wall_b]: {
        // ابراج
        Req: {
            food: 200,
            wood: 2000,
            stone: 1000,
            metal: 500,
            coin: 0,
            people: 0,
            time: 180,
        },
        condition: [
            {
                type: Common_Enum_1.ECondType.Building,
                lvl: 3,
                Type: Building_Enum_1.EBuildingType.WALL,
            },
            { type: Common_Enum_1.ECondType.Study, lvl: 2, Type: Study_Enum_1.EStudyType.safe },
        ],
        Title: "ابراج",
        image: "Res/tech/defense02.jpg",
        Desc: `لا تنحصر وظيفة الابراج فى الرقابة على المدن, 
            ولكن تلعب دور هام فى الدفاع عن المدينة عند الهجوم عليها.
            تتميز الابراج بارتفاعتها الشاهقة مما يجعل منها افضلية هجومية ودفاعية ايضا`,
        Power: {
            vit: 400,
            attack: 19,
            defence: 25,
            damage: { min: 12, max: 15 },
            break: 0,
            anti_break: 4,
            strike: 15,
            immunity: 5,
            eating: 0,
            speed: 0,
            capacity: 0,
        },
        dismess: {
            food: 60,
            wood: 600,
            stone: 300,
            metal: 150,
        },
        wall_space: 3,
        gainedPrestige: 6,
        Icon: "Res/tech/defense02.jpg",
    },
    [Army_Enum_1.EArmyType.wall_c]: {
        // احجار  متساقطة
        Req: {
            food: 600,
            wood: 0,
            stone: 8000,
            metal: 0,
            coin: 0,
            time: 600,
            people: 0,
        },
        condition: [
            {
                type: Common_Enum_1.ECondType.Building,
                lvl: 5,
                Type: Building_Enum_1.EBuildingType.WALL,
            },
            { type: Common_Enum_1.ECondType.Study, lvl: 6, Type: Study_Enum_1.EStudyType.safe },
        ],
        Title: "احجار متساقطة",
        image: "Res/tech/defense03.jpg",
        Desc: `الاحجار المتساقطة من اقوى الاسلحة الدفاعية للمدينة .
                يمكن الاعتماد عليها بالحاق الضرر الجسيم للمعتدى`,
        Power: {
            vit: 0,
            attack: 0,
            defence: 40,
            damage: { min: 40, max: 60 },
            break: 0,
            anti_break: 0,
            strike: 0,
            immunity: 0,
            eating: 0,
            speed: 0,
            capacity: 0,
        },
        dismess: {
            food: 180,
            wood: 0,
            stone: 2400,
            metal: 0,
        },
        wall_space: 5,
        gainedPrestige: 27,
        Icon: "Res/tech/defense03.jpg",
    },
};


/***/ },

/***/ "../Common/Enum/Army.Enum.ts"
/*!***********************************!*\
  !*** ../Common/Enum/Army.Enum.ts ***!
  \***********************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.EArmyPlace = exports.EArmyPlaceOnHero = exports.EArmyBuildDivideBy = exports.EArmyWeapon = exports.EHeroArmyTypeList = exports.EArmyType = void 0;
var EArmyType;
(function (EArmyType) {
    EArmyType[EArmyType["NONE"] = 0] = "NONE";
    EArmyType[EArmyType["army_a"] = 1] = "army_a";
    EArmyType[EArmyType["army_b"] = 2] = "army_b";
    EArmyType[EArmyType["army_c"] = 3] = "army_c";
    EArmyType[EArmyType["army_d"] = 4] = "army_d";
    EArmyType[EArmyType["army_e"] = 5] = "army_e";
    EArmyType[EArmyType["army_f"] = 6] = "army_f";
    EArmyType[EArmyType["spies"] = 7] = "spies";
    EArmyType[EArmyType["wall_a"] = 8] = "wall_a";
    EArmyType[EArmyType["wall_b"] = 9] = "wall_b";
    EArmyType[EArmyType["wall_c"] = 10] = "wall_c";
})(EArmyType || (exports.EArmyType = EArmyType = {}));
exports.EHeroArmyTypeList = [
    "army_a",
    "army_b",
    "army_c",
    "army_d",
    "army_e",
    "army_f",
];
var EArmyWeapon;
(function (EArmyWeapon) {
    EArmyWeapon[EArmyWeapon["NONE"] = 0] = "NONE";
    EArmyWeapon[EArmyWeapon["weapon_a"] = 1] = "weapon_a";
    EArmyWeapon[EArmyWeapon["weapon_b"] = 2] = "weapon_b";
    EArmyWeapon[EArmyWeapon["weapon_c"] = 3] = "weapon_c";
    EArmyWeapon[EArmyWeapon["weapon_d"] = 4] = "weapon_d";
    EArmyWeapon[EArmyWeapon["weapon_e"] = 5] = "weapon_e";
    EArmyWeapon[EArmyWeapon["weapon_f"] = 6] = "weapon_f";
})(EArmyWeapon || (exports.EArmyWeapon = EArmyWeapon = {}));
var EArmyBuildDivideBy;
(function (EArmyBuildDivideBy) {
    EArmyBuildDivideBy[EArmyBuildDivideBy["none"] = 0] = "none";
    EArmyBuildDivideBy[EArmyBuildDivideBy["time"] = 1] = "time";
    EArmyBuildDivideBy[EArmyBuildDivideBy["amount"] = 2] = "amount";
})(EArmyBuildDivideBy || (exports.EArmyBuildDivideBy = EArmyBuildDivideBy = {}));
var EArmyPlaceOnHero;
(function (EArmyPlaceOnHero) {
    EArmyPlaceOnHero[EArmyPlaceOnHero["f_1"] = 0] = "f_1";
    EArmyPlaceOnHero[EArmyPlaceOnHero["f_2"] = 1] = "f_2";
    EArmyPlaceOnHero[EArmyPlaceOnHero["f_3"] = 2] = "f_3";
    EArmyPlaceOnHero[EArmyPlaceOnHero["b_1"] = 3] = "b_1";
    EArmyPlaceOnHero[EArmyPlaceOnHero["b_2"] = 4] = "b_2";
    EArmyPlaceOnHero[EArmyPlaceOnHero["b_3"] = 5] = "b_3";
})(EArmyPlaceOnHero || (exports.EArmyPlaceOnHero = EArmyPlaceOnHero = {}));
var EArmyPlace;
(function (EArmyPlace) {
})(EArmyPlace || (exports.EArmyPlace = EArmyPlace = {}));
// Elkaisar.Config.AttackTypeNorm = 0;
// Elkaisar.Config.AttackTypeBreak = 1;
// Elkaisar.Config.AttackTypeStrike = 2;
// Elkaisar.Config.AttackTypeStrikeAndBreak = 3;


/***/ },

/***/ "../Common/Enum/Building.Enum.ts"
/*!***************************************!*\
  !*** ../Common/Enum/Building.Enum.ts ***!
  \***************************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.EBuildingType = void 0;
var EBuildingType;
(function (EBuildingType) {
    EBuildingType[EBuildingType["SPACE"] = 0] = "SPACE";
    EBuildingType[EBuildingType["COTTAGE"] = 1] = "COTTAGE";
    EBuildingType[EBuildingType["STORE"] = 2] = "STORE";
    EBuildingType[EBuildingType["BARRACKS"] = 3] = "BARRACKS";
    EBuildingType[EBuildingType["STABL"] = 4] = "STABL";
    EBuildingType[EBuildingType["WORKSHOP"] = 5] = "WORKSHOP";
    EBuildingType[EBuildingType["THEATER"] = 6] = "THEATER";
    EBuildingType[EBuildingType["STATION"] = 7] = "STATION";
    EBuildingType[EBuildingType["UNIVERSITY"] = 8] = "UNIVERSITY";
    EBuildingType[EBuildingType["ACADEMY"] = 9] = "ACADEMY";
    EBuildingType[EBuildingType["WORSHIP"] = 10] = "WORSHIP";
    EBuildingType[EBuildingType["HOSPITAL"] = 11] = "HOSPITAL";
    EBuildingType[EBuildingType["PALACE"] = 12] = "PALACE";
    EBuildingType[EBuildingType["WALL"] = 13] = "WALL";
    EBuildingType[EBuildingType["MARKET"] = 14] = "MARKET";
    EBuildingType[EBuildingType["WOOD"] = 15] = "WOOD";
    EBuildingType[EBuildingType["FARM"] = 16] = "FARM";
    EBuildingType[EBuildingType["MINE"] = 17] = "MINE";
    EBuildingType[EBuildingType["STONE"] = 18] = "STONE";
    EBuildingType[EBuildingType["SEAPORT"] = 19] = "SEAPORT";
    EBuildingType[EBuildingType["LIGHTHOUSE"] = 20] = "LIGHTHOUSE";
})(EBuildingType || (exports.EBuildingType = EBuildingType = {}));


/***/ },

/***/ "../Common/Enum/Common.Enum.ts"
/*!*************************************!*\
  !*** ../Common/Enum/Common.Enum.ts ***!
  \*************************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.EUserLogAction = exports.MARKET_MAX_OUT_TRANS = exports.ESpyOn = exports.EFightAttribute = exports.EExchangeCat = exports.ECondType = void 0;
var ECondType;
(function (ECondType) {
    ECondType[ECondType["NONE"] = 0] = "NONE";
    ECondType[ECondType["Building"] = 1] = "Building";
    ECondType[ECondType["Resource"] = 2] = "Resource";
    ECondType[ECondType["Study"] = 3] = "Study";
    ECondType[ECondType["Item"] = 4] = "Item";
    ECondType[ECondType["population"] = 5] = "population";
    ECondType[ECondType["Quest"] = 6] = "Quest";
})(ECondType || (exports.ECondType = ECondType = {}));
var EExchangeCat;
(function (EExchangeCat) {
    EExchangeCat["TradeDaily"] = "trade-daily";
    EExchangeCat["TradeMilli"] = "trade-milli";
    EExchangeCat["TradePlassing"] = "trade-plassing";
    EExchangeCat["TradeEvent"] = "trade-event";
    EExchangeCat["TradeAll"] = "trade-all";
    EExchangeCat["None"] = "";
})(EExchangeCat || (exports.EExchangeCat = EExchangeCat = {}));
var EFightAttribute;
(function (EFightAttribute) {
    EFightAttribute["attack"] = "attack";
    EFightAttribute["defence"] = "defence";
    EFightAttribute["vit"] = "vit";
    EFightAttribute["damage"] = "damage";
    EFightAttribute["break"] = "break";
    EFightAttribute["anti_break"] = "anti_break";
    EFightAttribute["strike"] = "strike";
    EFightAttribute["immunity"] = "immunity";
})(EFightAttribute || (exports.EFightAttribute = EFightAttribute = {}));
var ESpyOn;
(function (ESpyOn) {
    ESpyOn["city"] = "city";
    ESpyOn["barrary"] = "barrary";
})(ESpyOn || (exports.ESpyOn = ESpyOn = {}));
exports.MARKET_MAX_OUT_TRANS = 10;
var EUserLogAction;
(function (EUserLogAction) {
    EUserLogAction[EUserLogAction["Login"] = 0] = "Login";
    EUserLogAction[EUserLogAction["Logout"] = 1] = "Logout";
    EUserLogAction[EUserLogAction["FailedLogin"] = 2] = "FailedLogin";
    EUserLogAction[EUserLogAction["PasswordChange"] = 3] = "PasswordChange";
    EUserLogAction[EUserLogAction["ProfileUpdate"] = 4] = "ProfileUpdate";
    EUserLogAction[EUserLogAction["AccountCreation"] = 5] = "AccountCreation";
    EUserLogAction[EUserLogAction["AccountDeletion"] = 6] = "AccountDeletion";
    EUserLogAction[EUserLogAction["TwoFactorAuthEnabled"] = 7] = "TwoFactorAuthEnabled";
    EUserLogAction[EUserLogAction["TwoFactorAuthDisabled"] = 8] = "TwoFactorAuthDisabled";
})(EUserLogAction || (exports.EUserLogAction = EUserLogAction = {}));


/***/ },

/***/ "../Common/Enum/Study.Enum.ts"
/*!************************************!*\
  !*** ../Common/Enum/Study.Enum.ts ***!
  \************************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.EStudyType = void 0;
var EStudyType;
(function (EStudyType) {
    EStudyType["farming"] = "farming";
    EStudyType["wooding"] = "wooding";
    EStudyType["stoning"] = "stoning";
    EStudyType["mining"] = "mining";
    EStudyType["accounting"] = "accounting";
    EStudyType["storing"] = "storing";
    EStudyType["building"] = "building";
    EStudyType["scholership"] = "scholership";
    EStudyType["maintenace"] = "maintenace";
    EStudyType["infantry"] = "infantry";
    EStudyType["riding"] = "riding";
    EStudyType["army"] = "army";
    EStudyType["spying"] = "spying";
    EStudyType["leader"] = "leader";
    EStudyType["safe"] = "safe";
    EStudyType["medicine"] = "medicine";
    EStudyType["logistic"] = "logistic";
    EStudyType["navigating"] = "navigating";
    EStudyType["supplying"] = "supplying";
})(EStudyType || (exports.EStudyType = EStudyType = {}));


/***/ },

/***/ "../Common/Game/Item/Item.Enums.ts"
/*!*****************************************!*\
  !*** ../Common/Game/Item/Item.Enums.ts ***!
  \*****************************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.EItemBoxFor = exports.EItemKey = exports.EItemUseFor = exports.EItemTable = exports.EItemUse = void 0;
var EItemUse;
(function (EItemUse) {
    EItemUse[EItemUse["none"] = 0] = "none";
    EItemUse[EItemUse["once"] = 1] = "once";
    EItemUse[EItemUse["box"] = 2] = "box";
    EItemUse[EItemUse["many"] = 3] = "many";
})(EItemUse || (exports.EItemUse = EItemUse = {}));
var EItemTable;
(function (EItemTable) {
    EItemTable["matrial_main"] = "matrial_main";
    EItemTable["matrial_acce"] = "matrial_acce";
    EItemTable["matrial_product"] = "matrial_product";
    EItemTable["matrial_box"] = "matrial_box";
    EItemTable["matrial_luxury"] = "matrial_luxury";
    EItemTable["matrial_flags"] = "matrial_flags";
})(EItemTable || (exports.EItemTable = EItemTable = {}));
var EItemUseFor;
(function (EItemUseFor) {
    EItemUseFor["add_xp"] = "add_xp";
    EItemUseFor["retreat_points"] = "retreat_points";
    EItemUseFor["add_power"] = "add_power";
    EItemUseFor["add_loy"] = "add_loy";
    EItemUseFor["upgrade_guild"] = "upgrade_guild";
    EItemUseFor["building_acce"] = "building_acce";
    EItemUseFor["jop_acce"] = "jop_acce";
    EItemUseFor["reset_helper"] = "reset_helper";
    EItemUseFor["army_build_acce"] = "army_build_acce";
    EItemUseFor["add_city_builder"] = "add_city_builder";
    EItemUseFor["change_g_slog"] = "change_g_slog";
    EItemUseFor["change_player_name"] = "change_player_name";
    EItemUseFor["refresh_theater_with_mat"] = "refresh_theater_with_mat";
    EItemUseFor["increase-city-util"] = "increase-city-util";
    EItemUseFor["study_acce"] = "study_acce";
    EItemUseFor["add_medal"] = "add_medal";
    EItemUseFor["add-god-points"] = "add-god-points";
    EItemUseFor["acce-arriving-deal"] = "acce-arriving-deal";
    EItemUseFor["downgrade-building-lvl"] = "downgrade-building-lvl";
    EItemUseFor["open-fourth-cell"] = "open-fourth-cell";
    EItemUseFor["addArenaExp"] = "addArenaExp";
    EItemUseFor["addArenaAtt"] = "addArenaAtt";
})(EItemUseFor || (exports.EItemUseFor = EItemUseFor = {}));
var EItemKey;
(function (EItemKey) {
    EItemKey["motiv_60"] = "motiv_60";
    EItemKey["motiv_7"] = "motiv_7";
    EItemKey["motiv_pack"] = "motiv_pack";
    EItemKey["prot_pop"] = "prot_pop";
    EItemKey["peace"] = "peace";
    EItemKey["a_play"] = "a_play";
    EItemKey["flag"] = "flag";
    EItemKey["hidden_box"] = "hidden_box";
    EItemKey["help_house_chng"] = "help_house_chng";
    EItemKey["family_slogan"] = "family_slogan";
    EItemKey["union_slogan"] = "union_slogan";
    EItemKey["union_declar"] = "union_declar";
    EItemKey["union_era"] = "union_era";
    EItemKey["expan_plan"] = "expan_plan";
    EItemKey["marcus_scheme"] = "marcus_scheme";
    EItemKey["freedom_help"] = "freedom_help";
    EItemKey["medical_moun"] = "medical_moun";
    EItemKey["mediacl_statue"] = "mediacl_statue";
    EItemKey["sparta_stab"] = "sparta_stab";
    EItemKey["marmlo_helmet"] = "marmlo_helmet";
    EItemKey["qulinds_shaft"] = "qulinds_shaft";
    EItemKey["march_prot"] = "march_prot";
    EItemKey["medal_ceasro"] = "medal_ceasro";
    EItemKey["medal_den"] = "medal_den";
    EItemKey["medal_leo"] = "medal_leo";
    EItemKey["ceaser_eagle"] = "ceaser_eagle";
    EItemKey["medal_pack"] = "medal_pack";
    EItemKey["rec_letter"] = "rec_letter";
    EItemKey["retreat_point"] = "retreat_point";
    EItemKey["hero_train"] = "hero_train";
    EItemKey["random_move"] = "random_move";
    EItemKey["certain_move"] = "certain_move";
    EItemKey["spies"] = "spies";
    EItemKey["change_name"] = "change_name";
    EItemKey["buy_voucher"] = "buy_voucher";
    EItemKey["sell_voucher"] = "sell_voucher";
    EItemKey["black_ore"] = "black_ore";
    EItemKey["silver_ore"] = "silver_ore";
    EItemKey["red_ore"] = "red_ore";
    EItemKey["red_bursa"] = "red_bursa";
    EItemKey["power_bright"] = "power_bright";
    EItemKey["bright_medal"] = "bright_medal";
    EItemKey["bright_piece"] = "bright_piece";
    EItemKey["bright_slo"] = "bright_slo";
    EItemKey["trumpet"] = "trumpet";
    EItemKey["repel_trumpet_1"] = "repel_trumpet_1";
    EItemKey["repel_trumpet_2"] = "repel_trumpet_2";
    EItemKey["repel_medal"] = "repel_medal";
    EItemKey["warrior_medal"] = "warrior_medal";
    EItemKey["demigod_papirus"] = "demigod_papirus";
    EItemKey["demigod_power"] = "demigod_power";
    EItemKey["bronze_horn"] = "bronze_horn";
    EItemKey["silver_horn"] = "silver_horn";
    EItemKey["gold_horn"] = "gold_horn";
    EItemKey["bronze_order"] = "bronze_order";
    EItemKey["silver_order"] = "silver_order";
    EItemKey["gold_order"] = "gold_order";
    EItemKey["evil_pass"] = "evil_pass";
    EItemKey["evil_army_pass"] = "evil_army_pass";
    EItemKey["ancient_coins"] = "ancient_coins";
    EItemKey["apollo_pass"] = "apollo_pass";
    EItemKey["drachma"] = "drachma";
    EItemKey["medalofcraft"] = "medalofcraft";
    EItemKey["miraculous_coins"] = "miraculous_coins";
    EItemKey["owl_of_minerva"] = "owl_of_minerva";
    EItemKey["shining_coin"] = "shining_coin";
    EItemKey["gold_cup"] = "gold_cup";
    EItemKey["bronze_cup"] = "bronze_cup";
    EItemKey["silver_cup"] = "silver_cup";
    EItemKey["christmas_stocking"] = "christmas_stocking";
    EItemKey["christmas_tree"] = "christmas_tree";
    EItemKey["corn_seed"] = "corn_seed";
    EItemKey["courage_medal"] = "courage_medal";
    EItemKey["victory_medal"] = "victory_medal";
    EItemKey["king_medal"] = "king_medal";
    EItemKey["debris"] = "debris";
    EItemKey["essence"] = "essence";
    EItemKey["spartans"] = "spartans";
    EItemKey["spartans_pack"] = "spartans_pack";
    EItemKey["spartans_relics"] = "spartans_relics";
    EItemKey["spartans_flag_pack"] = "spartans_flag_pack";
    EItemKey["thor_stone"] = "thor_stone";
    EItemKey["thor_pergament"] = "thor_pergament";
    EItemKey["thor_fragment"] = "thor_fragment";
    EItemKey["sol_stone"] = "sol_stone";
    EItemKey["sol_pergament"] = "sol_pergament";
    EItemKey["sol_fragment"] = "sol_fragment";
    EItemKey["luna_stone"] = "luna_stone";
    EItemKey["luna_pergament"] = "luna_pergament";
    EItemKey["luna_fragment"] = "luna_fragment";
    EItemKey["hephaestus_pieces_a"] = "hephaestus_pieces_a";
    EItemKey["hephaestus_pieces_b"] = "hephaestus_pieces_b";
    EItemKey["mars_pieces_a"] = "mars_pieces_a";
    EItemKey["mars_pieces_b"] = "mars_pieces_b";
    EItemKey["mars_pieces_c"] = "mars_pieces_c";
    EItemKey["soul_of_mars"] = "soul_of_mars";
    EItemKey["gold_bar_piece"] = "gold_bar_piece";
    EItemKey["gold_bar"] = "gold_bar";
    EItemKey["silver_bar"] = "silver_bar";
    EItemKey["silver_bar_pack"] = "silver_bar_pack";
    EItemKey["gold_bar_pack"] = "gold_bar_pack";
    EItemKey["bronze_bar"] = "bronze_bar";
    EItemKey["high_glory_stone"] = "high_glory_stone";
    EItemKey["medium_glory_stone"] = "medium_glory_stone";
    EItemKey["low_glory_stone"] = "low_glory_stone";
    EItemKey["festival_blue"] = "festival_blue";
    EItemKey["festival_gray"] = "festival_gray";
    EItemKey["festival_green"] = "festival_green";
    EItemKey["festival_orange"] = "festival_orange";
    EItemKey["festival_purple"] = "festival_purple";
    EItemKey["festival_special"] = "festival_special";
    EItemKey["festival_yellow"] = "festival_yellow";
    EItemKey["ghostemblem"] = "ghostemblem";
    EItemKey["god_spring"] = "god_spring";
    EItemKey["huizhang"] = "huizhang";
    EItemKey["taffy"] = "taffy";
    EItemKey["rose_flower"] = "rose_flower";
    EItemKey["peace_emblem"] = "peace_emblem";
    EItemKey["peace_flag"] = "peace_flag";
    EItemKey["peace_medal"] = "peace_medal";
    EItemKey["staduim_orange"] = "staduim_orange";
    EItemKey["staduim_red"] = "staduim_red";
    EItemKey["staduim_blue"] = "staduim_blue";
    EItemKey["king"] = "king";
    EItemKey["snowman"] = "snowman";
    EItemKey["old_gitar"] = "old_gitar";
    EItemKey["archit_a"] = "archit_a";
    EItemKey["archit_b"] = "archit_b";
    EItemKey["archit_c"] = "archit_c";
    EItemKey["archit_d"] = "archit_d";
    EItemKey["archim_a"] = "archim_a";
    EItemKey["archim_b"] = "archim_b";
    EItemKey["archim_c"] = "archim_c";
    EItemKey["archim_d"] = "archim_d";
    EItemKey["polit_a"] = "polit_a";
    EItemKey["polit_b"] = "polit_b";
    EItemKey["polit_c"] = "polit_c";
    EItemKey["polit_d"] = "polit_d";
    EItemKey["bread"] = "bread";
    EItemKey["fruit"] = "fruit";
    EItemKey["milk"] = "milk";
    EItemKey["meat"] = "meat";
    EItemKey["coach"] = "coach";
    EItemKey["train_acce_30"] = "train_acce_30";
    EItemKey["train_acce_50"] = "train_acce_50";
    EItemKey["exp_hero_8"] = "exp_hero_8";
    EItemKey["exp_hero_30"] = "exp_hero_30";
    EItemKey["exp_hero_100"] = "exp_hero_100";
    EItemKey["hero_back"] = "hero_back";
    EItemKey["shopping_car"] = "shopping_car";
    EItemKey["wall_acce"] = "wall_acce";
    EItemKey["powder_keg"] = "powder_keg";
    EItemKey["wheat_1"] = "wheat_1";
    EItemKey["wheat_7"] = "wheat_7";
    EItemKey["stone_1"] = "stone_1";
    EItemKey["stone_7"] = "stone_7";
    EItemKey["wood_1"] = "wood_1";
    EItemKey["wood_7"] = "wood_7";
    EItemKey["metal_1"] = "metal_1";
    EItemKey["metal_7"] = "metal_7";
    EItemKey["coin_1"] = "coin_1";
    EItemKey["coin_7"] = "coin_7";
    EItemKey["gift_box"] = "gift_box";
    EItemKey["wood_box"] = "wood_box";
    EItemKey["golden_box"] = "golden_box";
    EItemKey["luck_play"] = "luck_play";
    EItemKey["army_box"] = "army_box";
    EItemKey["tagned_3p"] = "tagned_3p";
    EItemKey["tagned_4p"] = "tagned_4p";
    EItemKey["tagned_5p"] = "tagned_5p";
    EItemKey["tagned_6p"] = "tagned_6p";
    EItemKey["tagned_7p"] = "tagned_7p";
    EItemKey["tagned_8p"] = "tagned_8p";
    EItemKey["necklace_1"] = "necklace_1";
    EItemKey["necklace_2"] = "necklace_2";
    EItemKey["necklace_3"] = "necklace_3";
    EItemKey["necklace_4"] = "necklace_4";
    EItemKey["necklace_5"] = "necklace_5";
    EItemKey["necklace_6"] = "necklace_6";
    EItemKey["easter_pack"] = "easter_pack";
    EItemKey["easter_box"] = "easter_box";
    EItemKey["medal_bronz"] = "medal_bronz";
    EItemKey["medal_silver"] = "medal_silver";
    EItemKey["medal_gold"] = "medal_gold";
    EItemKey["army_all_1"] = "army_all_1";
    EItemKey["army_all_2"] = "army_all_2";
    EItemKey["army_all_3"] = "army_all_3";
    EItemKey["army_a_100"] = "army_a_100";
    EItemKey["army_b_100"] = "army_b_100";
    EItemKey["army_c_100"] = "army_c_100";
    EItemKey["army_d_100"] = "army_d_100";
    EItemKey["army_e_100"] = "army_e_100";
    EItemKey["army_f_100"] = "army_f_100";
    EItemKey["army_a_1000"] = "army_a_1000";
    EItemKey["army_b_1000"] = "army_b_1000";
    EItemKey["army_c_1000"] = "army_c_1000";
    EItemKey["army_d_1000"] = "army_d_1000";
    EItemKey["army_e_1000"] = "army_e_1000";
    EItemKey["army_f_1000"] = "army_f_1000";
    EItemKey["t_map"] = "t_map";
    EItemKey["food_a"] = "food_a";
    EItemKey["food_b"] = "food_b";
    EItemKey["food_c"] = "food_c";
    EItemKey["food_d"] = "food_d";
    EItemKey["wood_a"] = "wood_a";
    EItemKey["wood_b"] = "wood_b";
    EItemKey["wood_c"] = "wood_c";
    EItemKey["wood_d"] = "wood_d";
    EItemKey["stone_a"] = "stone_a";
    EItemKey["stone_b"] = "stone_b";
    EItemKey["stone_c"] = "stone_c";
    EItemKey["stone_d"] = "stone_d";
    EItemKey["metal_a"] = "metal_a";
    EItemKey["metal_b"] = "metal_b";
    EItemKey["metal_c"] = "metal_c";
    EItemKey["metal_d"] = "metal_d";
    EItemKey["coin_a"] = "coin_a";
    EItemKey["coin_b"] = "coin_b";
    EItemKey["coin_c"] = "coin_c";
    EItemKey["coin_d"] = "coin_d";
    EItemKey["beginner_back_1"] = "beginner_back_1";
    EItemKey["beginner_back_2"] = "beginner_back_2";
    EItemKey["beginner_back_3"] = "beginner_back_3";
    EItemKey["beginner_back_4"] = "beginner_back_4";
    EItemKey["beginner_back_5"] = "beginner_back_5";
    EItemKey["beginner_back_6"] = "beginner_back_6";
    EItemKey["beginner_back_7"] = "beginner_back_7";
    EItemKey["beginner_back_8"] = "beginner_back_8";
    EItemKey["beginner_back_9"] = "beginner_back_9";
    EItemKey["beginner_back_10"] = "beginner_back_10";
    EItemKey["golden_supply_crate"] = "golden_supply_crate";
    EItemKey["king_chest"] = "king_chest";
    EItemKey["roma_colosseum_box"] = "roma_colosseum_box";
    EItemKey["senate_golden_box"] = "senate_golden_box";
    EItemKey["senate_platinum_box"] = "senate_platinum_box";
    EItemKey["senate_silver_box"] = "senate_silver_box";
    EItemKey["silver_supply_crate"] = "silver_supply_crate";
    EItemKey["sol_chest"] = "sol_chest";
    EItemKey["vertumnus_box"] = "vertumnus_box";
    EItemKey["winter_pack"] = "winter_pack";
    EItemKey["platinum_supply_crate"] = "platinum_supply_crate";
    EItemKey["diamond_supply_crate"] = "diamond_supply_crate";
    EItemKey["lost_pack"] = "lost_pack";
    EItemKey["letter_pendant_up_2"] = "letter_pendant_up_2";
    EItemKey["letter_pendant_up_3"] = "letter_pendant_up_3";
    EItemKey["letter_belt_up_2"] = "letter_belt_up_2";
    EItemKey["letter_belt_up_3"] = "letter_belt_up_3";
    EItemKey["letter_necklace_up_2"] = "letter_necklace_up_2";
    EItemKey["letter_necklace_up_3"] = "letter_necklace_up_3";
    EItemKey["letter_ring_up_2"] = "letter_ring_up_2";
    EItemKey["letter_ring_up_3"] = "letter_ring_up_3";
    EItemKey["letter_steed_up_2"] = "letter_steed_up_2";
    EItemKey["letter_steed_up_3"] = "letter_steed_up_3";
    EItemKey["letter_pendant_1"] = "letter_pendant_1";
    EItemKey["letter_pendant_2"] = "letter_pendant_2";
    EItemKey["letter_pendant_3"] = "letter_pendant_3";
    EItemKey["letter_belt_1"] = "letter_belt_1";
    EItemKey["letter_belt_2"] = "letter_belt_2";
    EItemKey["letter_belt_3"] = "letter_belt_3";
    EItemKey["letter_necklace_1"] = "letter_necklace_1";
    EItemKey["letter_necklace_2"] = "letter_necklace_2";
    EItemKey["letter_necklace_3"] = "letter_necklace_3";
    EItemKey["letter_ring_1"] = "letter_ring_1";
    EItemKey["letter_ring_2"] = "letter_ring_2";
    EItemKey["letter_ring_3"] = "letter_ring_3";
    EItemKey["letter_steed_1"] = "letter_steed_1";
    EItemKey["letter_steed_2"] = "letter_steed_2";
    EItemKey["letter_steed_3"] = "letter_steed_3";
    EItemKey["bronze_bar_pack"] = "bronze_bar_pack";
    EItemKey["RebelMessenger"] = "RebelMessenger";
    EItemKey["RebelMail"] = "RebelMail";
    EItemKey["TrialCertificate"] = "TrialCertificate";
    EItemKey["army_build_ex"] = "army_build_ex";
    EItemKey["army_all_pack"] = "army_all_pack";
    EItemKey["MinervaGem"] = "MinervaGem";
    EItemKey["MinervaHolyWater"] = "MinervaHolyWater";
    EItemKey["MinervaHolyWater_S"] = "MinervaHolyWater_S";
    EItemKey["MinervaHolyWater_M"] = "MinervaHolyWater_M";
    EItemKey["MinervaHolyWater_L"] = "MinervaHolyWater_L";
    EItemKey["MinervaHolyWaterPack"] = "MinervaHolyWaterPack";
    EItemKey["luxury_1"] = "luxury_1";
    EItemKey["luxury_2"] = "luxury_2";
    EItemKey["luxury_3"] = "luxury_3";
    EItemKey["luxury_4"] = "luxury_4";
    EItemKey["luxury_5"] = "luxury_5";
    EItemKey["luxury_6"] = "luxury_6";
    EItemKey["luxury_7"] = "luxury_7";
    EItemKey["luxury_8"] = "luxury_8";
    EItemKey["luxury_9"] = "luxury_9";
    EItemKey["flag_england"] = "flag_england";
    EItemKey["flag_france"] = "flag_france";
    EItemKey["flag_germany"] = "flag_germany";
    EItemKey["flag_magul"] = "flag_magul";
    EItemKey["flag_macdoni"] = "flag_macdoni";
    EItemKey["flag_roma"] = "flag_roma";
    EItemKey["flag_spain"] = "flag_spain";
    EItemKey["flag_cartaga"] = "flag_cartaga";
    EItemKey["flag_egypt"] = "flag_egypt";
    EItemKey["flag_greek"] = "flag_greek";
    EItemKey["law_1"] = "law_1";
    EItemKey["law_2"] = "law_2";
    EItemKey["law_3"] = "law_3";
    EItemKey["truce_pack"] = "truce_pack";
    EItemKey["prestige_slogan"] = "prestige_slogan";
    EItemKey["lion_slo"] = "lion_slo";
    EItemKey["lion_hrt"] = "lion_hrt";
    EItemKey["brave_slo"] = "brave_slo";
    EItemKey["horn_help"] = "horn_help";
    EItemKey["mili_slo"] = "mili_slo";
    EItemKey["god_point_5"] = "god_point_5";
    EItemKey["god_point_30"] = "god_point_30";
    EItemKey["god_point_75"] = "god_point_75";
    EItemKey["god_point_175"] = "god_point_175";
    EItemKey["god_point_750"] = "god_point_750";
    EItemKey["god_point_1k"] = "god_point_1k";
    EItemKey["god_point_2k"] = "god_point_2k";
    EItemKey["god_point_5k"] = "god_point_5k";
    EItemKey["god_point_10k"] = "god_point_10k";
    EItemKey["god_point_50k"] = "god_point_50k";
    EItemKey["gold_1"] = "gold_1";
    EItemKey["gold_5"] = "gold_5";
    EItemKey["gold_10"] = "gold_10";
    EItemKey["gold_25"] = "gold_25";
    EItemKey["gold_75"] = "gold_75";
    EItemKey["gold_100"] = "gold_100";
    EItemKey["gold_500"] = "gold_500";
    EItemKey["gold_1000"] = "gold_1000";
    EItemKey["arena_attempt_1"] = "arena_attempt_1";
    EItemKey["arena_attempt_5"] = "arena_attempt_5";
    EItemKey["arena_attempt_10"] = "arena_attempt_10";
    EItemKey["arena_exp_1"] = "arena_exp_1";
    EItemKey["arena_exp_5"] = "arena_exp_5";
    EItemKey["arena_exp_10"] = "arena_exp_10";
    EItemKey["arena_exp_25"] = "arena_exp_25";
    EItemKey["skill_book"] = "skill_book";
    EItemKey["offer_MinervaHolyWater_1"] = "offer_MinervaHolyWater_1";
    EItemKey["offer_MinervaHolyWater_2"] = "offer_MinervaHolyWater_2";
    EItemKey["offer_pronze_bar_i"] = "offer_pronze_bar_i";
    EItemKey["offer_pronze_bar_ii"] = "offer_pronze_bar_ii";
    EItemKey["offer_draco"] = "offer_draco";
    EItemKey["offer_flags"] = "offer_flags";
    EItemKey["offer_motiv"] = "offer_motiv";
    EItemKey["offer_gold_medal"] = "offer_gold_medal";
    EItemKey["offer_lux_1"] = "offer_lux_1";
    EItemKey["offer_lux_2"] = "offer_lux_2";
    EItemKey["offer_lux_3"] = "offer_lux_3";
    EItemKey["offer_lux_4"] = "offer_lux_4";
    EItemKey["offer_lux_5"] = "offer_lux_5";
    EItemKey["offer_lux_6"] = "offer_lux_6";
    EItemKey["offer_lux_7"] = "offer_lux_7";
    EItemKey["offer_king_medal"] = "offer_king_medal";
    EItemKey["offer_peace"] = "offer_peace";
    EItemKey["offer_silver_bar_1"] = "offer_silver_bar_1";
    EItemKey["offer_silver_bar_2"] = "offer_silver_bar_2";
    EItemKey["offer_silver_bar_3"] = "offer_silver_bar_3";
    EItemKey["offer_silver_bar_4"] = "offer_silver_bar_4";
    EItemKey["offer_silver_bar_5"] = "offer_silver_bar_5";
    EItemKey["offer_silver_bar_6"] = "offer_silver_bar_6";
    EItemKey["offer_truce"] = "offer_truce";
    EItemKey["offer_hammer"] = "offer_hammer";
    EItemKey["offer_hero_exp"] = "offer_hero_exp";
    EItemKey["offer_production_pack"] = "offer_production_pack";
    EItemKey["offer_production_pack_2"] = "offer_production_pack_2";
    EItemKey["offer_luck_1"] = "offer_luck_1";
    EItemKey["offer_luck_2"] = "offer_luck_2";
    EItemKey["offer_archit_1"] = "offer_archit_1";
    EItemKey["offer_archit_2"] = "offer_archit_2";
    EItemKey["offer_archit_3"] = "offer_archit_3";
    EItemKey["offer_archit_4"] = "offer_archit_4";
    EItemKey["offer_jop_1"] = "offer_jop_1";
    EItemKey["offer_jop_2"] = "offer_jop_2";
    EItemKey["offer_jop_3"] = "offer_jop_3";
    EItemKey["offer_jop_4"] = "offer_jop_4";
    EItemKey["offer_building_speed"] = "offer_building_speed";
    EItemKey["offer_marcus_scheme"] = "offer_marcus_scheme";
    EItemKey["offer_expan_plan"] = "offer_expan_plan";
    EItemKey["offer_expan_plan_2"] = "offer_expan_plan_2";
    EItemKey["offer_war"] = "offer_war";
})(EItemKey || (exports.EItemKey = EItemKey = {}));
var EItemBoxFor;
(function (EItemBoxFor) {
    EItemBoxFor["matrial"] = "Matrial";
    EItemBoxFor["Equipment"] = "Equipment";
})(EItemBoxFor || (exports.EItemBoxFor = EItemBoxFor = {}));


/***/ },

/***/ "../Common/Hero/Hero.Config.ts"
/*!*************************************!*\
  !*** ../Common/Hero/Hero.Config.ts ***!
  \*************************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.CHeroConfig = void 0;
exports.CHeroConfig = {
    0: {
        Icon: "/Res/hero/faceA1.png",
        Image: "/Res/hero/faceA1.jpg",
    },
    1: {
        Icon: "/Res/hero/faceA2.png",
        Image: "/Res/hero/faceA2.jpg",
    },
    2: {
        Icon: "/Res/hero/faceA3.png",
        Image: "/Res/hero/faceA3.jpg",
    },
    3: {
        Icon: "/Res/hero/faceA4.png",
        Image: "/Res/hero/faceA4.jpg",
    },
    4: {
        Icon: "/Res/hero/faceA5.png",
        Image: "/Res/hero/faceA5.jpg",
    },
    5: {
        Icon: "/Res/hero/faceA6.png",
        Image: "/Res/hero/faceA6.jpg",
    },
    6: {
        Icon: "/Res/hero/faceA7.png",
        Image: "/Res/hero/faceA7.jpg",
    },
    7: {
        Icon: "/Res/hero/faceA8.png",
        Image: "/Res/hero/faceA8.jpg",
    },
    8: {
        Icon: "/Res/hero/faceA9.png",
        Image: "/Res/hero/faceA9.jpg",
    },
    9: {
        Icon: "/Res/hero/faceA10.png",
        Image: "/Res/hero/faceA10.jpg",
    },
    10: {
        Icon: "/Res/hero/faceB1.png",
        Image: "/Res/hero/faceB1.jpg",
    },
    11: {
        Icon: "/Res/hero/faceB2.png",
        Image: "/Res/hero/faceB2.jpg",
    },
    12: {
        Icon: "/Res/hero/faceB3.png",
        Image: "/Res/hero/faceB3.jpg",
    },
    13: {
        Icon: "/Res/hero/faceB4.png",
        Image: "/Res/hero/faceB4.jpg",
    },
    14: {
        Icon: "/Res/hero/faceB5.png",
        Image: "/Res/hero/faceB5.jpg",
    },
    15: {
        Icon: "/Res/hero/faceB6.png",
        Image: "/Res/hero/faceB6.jpg",
    },
    16: {
        Icon: "/Res/hero/faceB7.png",
        Image: "/Res/hero/faceB7.jpg",
    },
    17: {
        Icon: "/Res/hero/faceB8.png",
        Image: "/Res/hero/faceB8.jpg",
    },
    18: {
        Icon: "/Res/hero/faceB9.png",
        Image: "/Res/hero/faceB9.jpg",
    },
    19: {
        Icon: "/Res/hero/faceB10.png",
        Image: "/Res/hero/faceB10.jpg",
    },
    20: {
        Icon: "/Res/hero/faceB10.png",
        Image: "/Res/hero/faceB10.jpg",
    },
};


/***/ },

/***/ "../Common/Hero/Hero.Enum.ts"
/*!***********************************!*\
  !*** ../Common/Hero/Hero.Enum.ts ***!
  \***********************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.EHeroPlaceInFightRound = exports.EHeroState = exports.PLAYER_FACES = exports.CHeroAvatar = void 0;
exports.CHeroAvatar = {
    // TODO
    0: "Res/hero/faceA1.jpg",
    1: "Res/hero/faceA1.jpg",
    2: "Res/hero/faceA2.jpg",
    3: "Res/hero/faceA3.jpg",
    4: "Res/hero/faceA4.jpg",
    5: "Res/hero/faceA5.jpg",
    6: "Res/hero/faceA6.jpg",
    7: "Res/hero/faceA7.jpg",
    8: "Res/hero/faceA8.jpg",
    9: "Res/hero/faceA9.jpg",
    10: "Res/hero/faceA10.jpg",
    11: "Res/hero/faceB1.jpg",
    12: "Res/hero/faceB2.jpg",
    13: "Res/hero/faceB3.jpg",
    14: "Res/hero/faceB4.jpg",
    15: "Res/hero/faceB5.jpg",
    16: "Res/hero/faceB6.jpg",
    17: "Res/hero/faceB7.jpg",
    18: "Res/hero/faceB8.jpg",
    19: "Res/hero/faceB9.jpg",
    20: "Res/hero/faceB9.jpg",
};
exports.PLAYER_FACES = [
    "Res/player/faceA1_.jpg",
    "Res/player/faceA2_.jpg",
    "Res/player/faceA3_.jpg",
    "Res/player/faceA4_.jpg",
    "Res/player/faceA5_.jpg",
    "Res/player/faceA6_.jpg",
    "Res/player/faceA7_.jpg",
    "Res/player/faceA8_.jpg",
    "Res/player/faceA9_.jpg",
    "Res/player/faceA10_.jpg",
    "Res/player/faceB1_.jpg",
    "Res/player/faceB2_.jpg",
    "Res/player/faceB3_.jpg",
    "Res/player/faceB4_.jpg",
    "Res/player/faceB5_.jpg",
    "Res/player/faceB6_.jpg",
    "Res/player/faceB7_.jpg",
    "Res/player/faceB8_.jpg",
    "Res/player/faceB9_.jpg",
];
var EHeroState;
(function (EHeroState) {
    EHeroState[EHeroState["IN_CITY"] = 1] = "IN_CITY";
    EHeroState[EHeroState["IN_BATTEL"] = 0] = "IN_BATTEL";
    EHeroState[EHeroState["IN_GARISON"] = 2] = "IN_GARISON";
})(EHeroState || (exports.EHeroState = EHeroState = {}));
var EHeroPlaceInFightRound;
(function (EHeroPlaceInFightRound) {
    EHeroPlaceInFightRound[EHeroPlaceInFightRound["DEF_1"] = 0] = "DEF_1";
    EHeroPlaceInFightRound[EHeroPlaceInFightRound["DEF_2"] = 1] = "DEF_2";
    EHeroPlaceInFightRound[EHeroPlaceInFightRound["DEF_3"] = 2] = "DEF_3";
    EHeroPlaceInFightRound[EHeroPlaceInFightRound["ATT_1"] = 3] = "ATT_1";
    EHeroPlaceInFightRound[EHeroPlaceInFightRound["ATT_2"] = 4] = "ATT_2";
    EHeroPlaceInFightRound[EHeroPlaceInFightRound["ATT_3"] = 5] = "ATT_3";
})(EHeroPlaceInFightRound || (exports.EHeroPlaceInFightRound = EHeroPlaceInFightRound = {}));


/***/ },

/***/ "../Common/Player/Config/Item.Config.ts"
/*!**********************************************!*\
  !*** ../Common/Player/Config/Item.Config.ts ***!
  \**********************************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.CItemConfig = void 0;
const Item_Enums_1 = __webpack_require__(/*! ../../Game/Item/Item.Enums */ "../Common/Game/Item/Item.Enums.ts");
exports.CItemConfig = {
    motiv_60: {
        image: "Res/items/item000.jpg",
        key: Item_Enums_1.EItemKey.motiv_60,
        name: "incBuildFormation60h.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 30,
        startingAmount: 1,
        desc: "incBuildFormation60h.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incBuildFormation60h.itemDesc",
    },
    motiv_pack: {
        image: "Res/items/Hammer.jpg",
        key: Item_Enums_1.EItemKey.motiv_pack,
        name: "Hammer.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "Hammer.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "Hammer.itemDesc",
    },
    motiv_7: {
        image: "Res/items/item001.jpg",
        key: Item_Enums_1.EItemKey.motiv_7,
        name: "incBuildFormation.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 50,
        desc: "incBuildFormation.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incBuildFormation.itemDesc",
    },
    prot_pop: {
        image: "Res/items/item002.jpg",
        key: Item_Enums_1.EItemKey.prot_pop,
        name: "incPopulation.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 50,
        desc: "incPopulation.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incPopulation.itemDesc",
    },
    peace: {
        image: "Res/items/item006.jpg",
        key: Item_Enums_1.EItemKey.peace,
        name: "player.peace.1.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 30,
        desc: "player.peace.1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "player.peace.1.itemDesc",
    },
    a_play: {
        image: "Res/items/item004.jpg",
        key: Item_Enums_1.EItemKey.a_play,
        name: "incCastleLoyalty.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 100,
        desc: "incCastleLoyalty.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incCastleLoyalty.itemDesc",
    },
    flag: {
        image: "Res/items/item025.jpg",
        key: Item_Enums_1.EItemKey.flag,
        name: "changeFlag.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 20,
        desc: "changeFlag.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "changeFlag.itemDesc",
    },
    help_house_chng: {
        image: "Res/items/item095.jpg",
        key: Item_Enums_1.EItemKey.help_house_chng,
        name: "changeBelieve.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 300,
        desc: "changeBelieve.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "changeBelieve.itemDesc",
    },
    family_slogan: {
        image: "Res/items/item097.jpg",
        key: Item_Enums_1.EItemKey.family_slogan,
        name: "changeLogo.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 50,
        desc: "changeLogo.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "changeLogo.itemDesc",
    },
    union_slogan: {
        image: "Res/items/item024.jpg",
        key: Item_Enums_1.EItemKey.union_slogan,
        name: "incAllianceLimitTo100.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 500,
        desc: "incAllianceLimitTo100.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incAllianceLimitTo100.itemDesc",
    },
    union_declar: {
        image: "Res/items/item154.jpg",
        key: Item_Enums_1.EItemKey.union_declar,
        name: "incAllianceLimitTo200.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 2000,
        desc: "incAllianceLimitTo200.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incAllianceLimitTo200.itemDesc",
    },
    union_era: {
        image: "Res/items/item155.jpg",
        key: Item_Enums_1.EItemKey.union_era,
        name: "incAllianceLimitTo300.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5000,
        desc: "incAllianceLimitTo300.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incAllianceLimitTo300.itemDesc",
    },
    expan_plan: {
        image: "Res/items/item003.jpg",
        key: Item_Enums_1.EItemKey.expan_plan,
        name: "upgradeCity.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 30,
        desc: "upgradeCity.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "upgradeCity.itemDesc",
    },
    marcus_scheme: {
        image: "Res/items/item005.jpg",
        key: Item_Enums_1.EItemKey.marcus_scheme,
        name: "upgradeBuilding.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 50,
        desc: "upgradeBuilding.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "upgradeBuilding.itemDesc",
    },
    freedom_help: {
        image: "Res/items/item149.jpg",
        key: Item_Enums_1.EItemKey.freedom_help,
        name: "independence.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 50,
        desc: "independence.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "independence.itemDesc",
    },
    medical_moun: {
        image: "Res/items/item009.jpg",
        key: Item_Enums_1.EItemKey.medical_moun,
        name: "reliveTroopAtWar.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 100,
        desc: "reliveTroopAtWar.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "reliveTroopAtWar.itemDesc",
    },
    mediacl_statue: {
        image: "Res/items/item153.jpg",
        key: Item_Enums_1.EItemKey.mediacl_statue,
        name: "relive50pTroopAtWar7D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 600,
        desc: "relive50pTroopAtWar7D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "relive50pTroopAtWar7D.itemDesc",
    },
    sparta_stab: {
        image: "Res/items/item017.jpg",
        key: Item_Enums_1.EItemKey.sparta_stab,
        name: "incArmyAttack1D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "incArmyAttack1D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incArmyAttack1D.itemDesc",
    },
    marmlo_helmet: {
        image: "Res/items/item019.jpg",
        key: Item_Enums_1.EItemKey.marmlo_helmet,
        name: "incArmyDefence1D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "incArmyDefence1D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incArmyDefence1D.itemDesc",
    },
    qulinds_shaft: {
        image: "Res/items/item018.jpg",
        key: Item_Enums_1.EItemKey.qulinds_shaft,
        name: "incArmyAttack7D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 60,
        desc: "incArmyAttack7D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incArmyAttack7D.itemDesc",
    },
    march_prot: {
        image: "Res/items/item020.jpg",
        key: Item_Enums_1.EItemKey.march_prot,
        name: "incArmyDefence7D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 60,
        desc: "incArmyDefence7D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incArmyDefence7D.itemDesc",
    },
    medal_ceasro: {
        image: "Res/items/item010.jpg",
        key: Item_Enums_1.EItemKey.medal_ceasro,
        name: "incHeroPolitics1D.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 50,
        desc: "incHeroPolitics1D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incHeroPolitics1D.itemDesc",
    },
    medal_den: {
        image: "Res/items/item011.jpg",
        key: Item_Enums_1.EItemKey.medal_den,
        name: "incHeroAttack1D.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 50,
        desc: "incHeroAttack1D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incHeroAttack1D.itemDesc",
    },
    medal_leo: {
        image: "Res/items/item012.jpg",
        key: Item_Enums_1.EItemKey.medal_leo,
        name: "incHeroDefence1D.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 50,
        desc: "incHeroDefence1D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incHeroDefence1D.itemDesc",
    },
    ceaser_eagle: {
        image: "Res/items/item016.jpg",
        key: Item_Enums_1.EItemKey.ceaser_eagle,
        name: "incArmyTroopLimit.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 30,
        desc: "incArmyTroopLimit.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incArmyTroopLimit.itemDesc",
    },
    medal_pack: {
        key: Item_Enums_1.EItemKey.medal_pack,
        image: "Res/items/preacherpacket.jpg",
        name: "preacherpacket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "preacherpacket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "preacherpacket.itemDesc",
    },
    skill_book: {
        key: Item_Enums_1.EItemKey.skill_book,
        image: "Res/items/skillBook.jpg",
        name: "SkillBook.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 1500,
        desc: "SkillBook.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "SkillBook.itemDesc",
    },
    rec_letter: {
        image: "Res/items/item013.jpg",
        key: Item_Enums_1.EItemKey.rec_letter,
        name: "refreshTavern.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5,
        desc: "refreshTavern.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "refreshTavern.itemDesc",
    },
    retreat_point: {
        image: "Res/items/item014.jpg",
        key: Item_Enums_1.EItemKey.retreat_point,
        name: "resetHero.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 8,
        desc: "resetHero.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "resetHero.itemDesc",
    },
    hero_train: {
        image: "Res/items/item089.jpg",
        key: Item_Enums_1.EItemKey.hero_train,
        name: "incWorkout.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 50,
        desc: "incWorkout.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "incWorkout.itemDesc",
    },
    random_move: {
        image: "Res/items/item007.jpg",
        key: Item_Enums_1.EItemKey.random_move,
        name: "moveCastle.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 30,
        desc: "moveCastle.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "moveCastle.itemDesc",
    },
    certain_move: {
        image: "Res/items/item008.jpg",
        key: Item_Enums_1.EItemKey.certain_move,
        name: "moveCastleAdv.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 90,
        desc: "moveCastleAdv.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "moveCastleAdv.itemDesc",
    },
    spies: {
        image: "Res/items/item027.jpg",
        key: Item_Enums_1.EItemKey.spies,
        name: "spy.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 20,
        desc: "spy.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "spy.itemDesc",
    },
    change_name: {
        image: "Res/items/item026.jpg",
        key: Item_Enums_1.EItemKey.change_name,
        name: "changePlayerName.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 200,
        startingAmount: 1,
        desc: "changePlayerName.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "changePlayerName.itemDesc",
    },
    buy_voucher: {
        image: "Res/items/buyVoucher.jpg",
        key: Item_Enums_1.EItemKey.buy_voucher,
        name: "",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5,
        desc: "",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "",
    },
    sell_voucher: {
        image: "Res/items/sellVoucher.jpg",
        key: Item_Enums_1.EItemKey.sell_voucher,
        name: "",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5,
        desc: "",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "",
    },
    black_ore: {
        image: "Res/items/Ore.black.jpg",
        key: Item_Enums_1.EItemKey.black_ore,
        name: "Ore.black.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "Ore.black.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "Ore.black.itemDesc",
    },
    silver_ore: {
        image: "Res/items/Ore.mirthial.jpg",
        key: Item_Enums_1.EItemKey.silver_ore,
        name: "Ore.mirthial.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "Ore.mirthial.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "Ore.mirthial.itemDesc",
    },
    red_ore: {
        image: "Res/items/Redore.jpg",
        key: Item_Enums_1.EItemKey.red_ore,
        name: "Redore.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "Redore.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "Redore.itemDesc",
    },
    red_bursa: {
        image: "Res/items/Redbursa.jpg",
        key: Item_Enums_1.EItemKey.red_bursa,
        name: "Redbursa.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "Redbursa.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "Redbursa.itemDesc",
    },
    power_bright: {
        image: "Res/items/PowerofBright.jpg",
        key: Item_Enums_1.EItemKey.power_bright,
        name: "PowerofBright.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 12,
        desc: "PowerofBright.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "PowerofBright.itemDesc",
    },
    bright_medal: {
        image: "Res/items/BrightMedal.jpg",
        key: Item_Enums_1.EItemKey.bright_medal,
        name: "BrightMedal.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "BrightMedal.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "BrightMedal.itemDesc",
    },
    bright_piece: {
        image: "Res/items/BrightPiece.jpg",
        key: Item_Enums_1.EItemKey.bright_piece,
        name: "BrightPiece.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "BrightPiece.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "BrightPiece.itemDesc",
    },
    bright_slo: {
        image: "Res/items/BrightEmblem.jpg",
        key: Item_Enums_1.EItemKey.bright_slo,
        name: "BrightEmblem.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "BrightEmblem.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "BrightEmblem.itemDesc",
    },
    trumpet: {
        image: "Res/items/item022.jpg",
        key: Item_Enums_1.EItemKey.trumpet,
        name: "speaker10.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 3,
        startingAmount: 1,
        desc: "speaker10.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "speaker10.itemDesc",
    },
    repel_trumpet_1: {
        image: "Res/items/Horn.repression.1.jpg",
        key: Item_Enums_1.EItemKey.repel_trumpet_1,
        name: "Horn.repression.1.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 15,
        desc: "Horn.repression.1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "Horn.repression.1.itemDesc",
    },
    repel_trumpet_2: {
        image: "Res/items/Horn.repression.2.jpg",
        key: Item_Enums_1.EItemKey.repel_trumpet_2,
        name: "Horn.repression.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 40,
        desc: "Horn.repression.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "Horn.repression.2.itemDesc",
    },
    repel_medal: {
        image: "Res/items/Trans.repression.jpg",
        key: Item_Enums_1.EItemKey.repel_medal,
        name: "Trans.repression.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "Trans.repression.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "Trans.repression.itemDesc",
    },
    warrior_medal: {
        image: "Res/items/warrior.jpg",
        key: Item_Enums_1.EItemKey.warrior_medal,
        name: "warrior.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "warrior.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "warrior.itemDesc",
    },
    bronze_horn: {
        image: "Res/items/CopperHorn.jpg",
        key: Item_Enums_1.EItemKey.bronze_horn,
        name: "CopperHorn.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "CopperHorn.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "CopperHorn.itemDesc",
    },
    silver_horn: {
        image: "Res/items/SilverHorn.jpg",
        key: Item_Enums_1.EItemKey.silver_horn,
        name: "SilverHorn.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "SilverHorn.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "SilverHorn.itemDesc",
    },
    gold_horn: {
        image: "Res/items/GoldHorn.jpg",
        key: Item_Enums_1.EItemKey.gold_horn,
        name: "GoldHorn.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "GoldHorn.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "GoldHorn.itemDesc",
    },
    bronze_order: {
        image: "Res/items/CopperOrder.jpg",
        key: Item_Enums_1.EItemKey.bronze_order,
        name: "CopperOrder.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "CopperOrder.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "CopperOrder.itemDesc",
    },
    silver_order: {
        image: "Res/items/SilverOrder.jpg",
        key: Item_Enums_1.EItemKey.silver_order,
        name: "SilverOrder.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "SilverOrder.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "SilverOrder.itemDesc",
    },
    gold_order: {
        image: "Res/items/GoldOrder.jpg",
        key: Item_Enums_1.EItemKey.gold_order,
        name: "GoldOrder.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "GoldOrder.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "GoldOrder.itemDesc",
    },
    evil_pass: {
        image: "Res/items/Evilstoken.jpg",
        key: Item_Enums_1.EItemKey.evil_pass,
        name: "EvilArmyPass.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 60,
        desc: "EvilArmyPass.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "EvilArmyPass.itemDesc",
    },
    evil_army_pass: {
        image: "Res/items/EvilArmyPass.jpg",
        key: Item_Enums_1.EItemKey.evil_army_pass,
        name: "Evilstoken.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 60,
        desc: "Evilstoken.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "Evilstoken.itemDesc",
    },
    bronze_bar: {
        key: Item_Enums_1.EItemKey.bronze_bar,
        image: "Res/items/BronzeBar.jpg",
        name: "BronzeBar.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 10,
        desc: "BronzeBar.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "BronzeBar.itemDesc",
    },
    bronze_bar_pack: {
        key: Item_Enums_1.EItemKey.bronze_bar_pack,
        image: "Res/items/BigBronzeBarPack.jpg",
        name: "BigBronzeBarPack.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "BigBronzeBarPack.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "BigBronzeBarPack.itemDesc",
    },
    RebelMessenger: {
        key: Item_Enums_1.EItemKey.RebelMessenger,
        image: "Res/items/RebelMessenger.jpg",
        name: "RebelMessenger.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "RebelMessenger.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "RebelMessenger.itemDesc",
    },
    RebelMail: {
        key: Item_Enums_1.EItemKey.RebelMail,
        image: "Res/items/RebelMail.jpg",
        name: "RebelMail.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "RebelMail.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "RebelMail.itemDesc",
    },
    TrialCertificate: {
        key: Item_Enums_1.EItemKey.TrialCertificate,
        image: "Res/items/TrialCertificate.jpg",
        name: "TrialCertificate.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "TrialCertificate.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "TrialCertificate.itemDesc",
    },
    ancient_coins: {
        image: "Res/items/AncientCoins.jpg",
        key: Item_Enums_1.EItemKey.ancient_coins,
        name: "AncientCoins.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "AncientCoins.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "AncientCoins.itemDesc",
    },
    apollo_pass: {
        image: "Res/items/ApolloPass.jpg",
        key: Item_Enums_1.EItemKey.apollo_pass,
        name: "ApolloPass.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "ApolloPass.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "ApolloPass.itemDesc",
    },
    drachma: {
        image: "Res/items/drachma.jpg",
        key: Item_Enums_1.EItemKey.drachma,
        name: "drachma.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "drachma.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "drachma.itemDesc",
    },
    festival_blue: {
        image: "Res/items/festival_blue.jpg",
        key: Item_Enums_1.EItemKey.festival_blue,
        name: "festival_blue.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "festival_blue.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "festival_blue.itemDesc",
    },
    festival_gray: {
        image: "Res/items/festival_gray.jpg",
        key: Item_Enums_1.EItemKey.festival_gray,
        name: "festival_gray.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "festival_gray.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "festival_gray.itemDesc",
    },
    festival_green: {
        image: "Res/items/festival_green.jpg",
        key: Item_Enums_1.EItemKey.festival_green,
        name: "festival_green.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "festival_green.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "festival_green.itemDesc",
    },
    festival_orange: {
        image: "Res/items/festival_orange.jpg",
        key: Item_Enums_1.EItemKey.festival_orange,
        name: "festival_orange.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "festival_orange.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "festival_orange.itemDesc",
    },
    festival_purple: {
        image: "Res/items/festival_purple.jpg",
        key: Item_Enums_1.EItemKey.festival_purple,
        name: "festival_purple.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "festival_purple.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "festival_purple.itemDesc",
    },
    festival_special: {
        image: "Res/items/festival_special.jpg",
        key: Item_Enums_1.EItemKey.festival_special,
        name: "festival_special.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "festival_special.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "festival_special.itemDesc",
    },
    festival_yellow: {
        image: "Res/items/festival_yellow.jpg",
        key: Item_Enums_1.EItemKey.festival_yellow,
        name: "festival_yellow.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "festival_yellow.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "festival_yellow.itemDesc",
    },
    ghostemblem: {
        image: "Res/items/ghostemblem.jpg",
        key: Item_Enums_1.EItemKey.ghostemblem,
        name: "ghostemblem.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "ghostemblem.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "ghostemblem.itemDesc",
    },
    god_spring: {
        image: "Res/items/GodSpring.jpg",
        key: Item_Enums_1.EItemKey.god_spring,
        name: "GodSpring.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "GodSpring.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "GodSpring.itemDesc",
    },
    huizhang: {
        image: "Res/items/huizhang.jpg",
        key: Item_Enums_1.EItemKey.huizhang,
        name: "huizhang.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "huizhang.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "huizhang.itemDesc",
    },
    taffy: {
        image: "Res/items/taffy.jpg",
        key: Item_Enums_1.EItemKey.taffy,
        name: "taffy.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        startingAmount: 1,
        desc: "taffy.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "taffy.itemDesc",
    },
    rose_flower: {
        image: "Res/items/RoseFlower.jpg",
        key: Item_Enums_1.EItemKey.rose_flower,
        name: "RoseFlower.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "RoseFlower.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "RoseFlower.itemDesc",
    },
    peace_emblem: {
        image: "Res/items/PeaceEmblem.jpg",
        key: Item_Enums_1.EItemKey.peace_emblem,
        name: "PeaceEmblem.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "PeaceEmblem.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "PeaceEmblem.itemDesc",
    },
    peace_flag: {
        image: "Res/items/PeaceFlag.jpg",
        key: Item_Enums_1.EItemKey.peace_flag,
        name: "PeaceFlag.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "PeaceFlag.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "PeaceFlag.itemDesc",
    },
    peace_medal: {
        image: "Res/items/PeaceMedal.jpg",
        key: Item_Enums_1.EItemKey.peace_medal,
        name: "PeaceMedal.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "PeaceMedal.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "PeaceMedal.itemDesc",
    },
    staduim_orange: {
        key: Item_Enums_1.EItemKey.staduim_orange,
        image: "Res/items/item163.jpg",
        name: "football01.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 0,
        desc: "football01.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "football01.itemDesc",
    },
    staduim_red: {
        key: Item_Enums_1.EItemKey.staduim_red,
        image: "Res/items/item164.jpg",
        name: "football03.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 0,
        desc: "football03.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "football03.itemDesc",
    },
    staduim_blue: {
        key: Item_Enums_1.EItemKey.staduim_blue,
        image: "Res/items/item165.jpg",
        name: "football02.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 0,
        desc: "football02.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "football02.itemDesc",
    },
    king: {
        key: Item_Enums_1.EItemKey.king,
        image: "Res/items/king.jpg",
        name: "king.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 0,
        desc: "king.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "king.itemDesc",
    },
    snowman: {
        key: Item_Enums_1.EItemKey.snowman,
        image: "Res/items/snowcity.jpg",
        name: "snowcity.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 0,
        desc: "snowcity.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "snowcity.itemDesc",
    },
    old_gitar: {
        key: Item_Enums_1.EItemKey.old_gitar,
        image: "Res/items/Lyre.jpg",
        name: "Lyre.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "Lyre.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "Lyre.itemDesc",
    },
    army_build_ex: {
        key: Item_Enums_1.EItemKey.army_build_ex,
        image: "Res/items/item150.jpg",
        name: "rebuilding.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "rebuilding.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "rebuilding.itemDesc",
    },
    medalofcraft: {
        key: Item_Enums_1.EItemKey.medalofcraft,
        image: "Res/items/medalofcraft.jpg",
        name: "medal.of.craft.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "medal.of.craft.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "medal.of.craft.itemDesc",
    },
    miraculous_coins: {
        key: Item_Enums_1.EItemKey.miraculous_coins,
        image: "Res/items/MiraculousCoins.jpg",
        name: "MiraculousCoins.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "MiraculousCoins.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "MiraculousCoins.itemDesc",
    },
    owl_of_minerva: {
        key: Item_Enums_1.EItemKey.owl_of_minerva,
        image: "Res/items/OwlofMinerva.jpg",
        name: "OwlofMinerva.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "OwlofMinerva.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "OwlofMinerva.itemDesc",
    },
    shining_coin: {
        key: Item_Enums_1.EItemKey.shining_coin,
        image: "Res/items/ShiningCoin.jpg",
        name: "ShiningCoin.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "ShiningCoin.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "ShiningCoin.itemDesc",
    },
    gold_cup: {
        key: Item_Enums_1.EItemKey.gold_cup,
        image: "Res/items/GoldCup.png",
        name: "GoldCup.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "GoldCup.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "GoldCup.itemDesc",
    },
    bronze_cup: {
        key: Item_Enums_1.EItemKey.bronze_cup,
        image: "Res/items/BronzeCup.png",
        name: "BronzeCup.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "BronzeCup.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "BronzeCup.itemDesc",
    },
    silver_cup: {
        key: Item_Enums_1.EItemKey.silver_cup,
        image: "Res/items/SilverCup.jpg",
        name: "SilverCup.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "SilverCup.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "SilverCup.itemDesc",
    },
    christmas_stocking: {
        key: Item_Enums_1.EItemKey.christmas_stocking,
        image: "Res/items/christmasstocking.jpg",
        name: "christmasstocking.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "christmasstocking.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "christmasstocking.itemDesc",
    },
    christmas_tree: {
        key: Item_Enums_1.EItemKey.christmas_tree,
        image: "Res/items/christmastree.jpg",
        name: "christmastree.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "christmastree.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "christmastree.itemDesc",
    },
    corn_seed: {
        key: Item_Enums_1.EItemKey.corn_seed,
        image: "Res/items/CornSeed.jpg",
        name: "CornSeed.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "CornSeed.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "CornSeed.itemDesc",
    },
    courage_medal: {
        key: Item_Enums_1.EItemKey.courage_medal,
        image: "Res/items/CourageMedal.jpg",
        name: "CourageMedal.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "CourageMedal.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "CourageMedal.itemDesc",
    },
    victory_medal: {
        key: Item_Enums_1.EItemKey.victory_medal,
        image: "Res/items/VictoryMedal.jpg",
        name: "VictoryMedal.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "VictoryMedal.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "VictoryMedal.itemDesc",
    },
    king_medal: {
        key: Item_Enums_1.EItemKey.king_medal,
        image: "Res/items/KingMedal.jpg",
        name: "KingMedal.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "KingMedal.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "KingMedal.itemDesc",
    },
    debris: {
        key: Item_Enums_1.EItemKey.debris,
        image: "Res/items/debris.jpg",
        name: "debris.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "debris.funcDesc",
        startingAmount: 1,
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "debris.itemDesc",
    },
    essence: {
        key: Item_Enums_1.EItemKey.essence,
        image: "Res/items/essence.jpg",
        name: "essence.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "essence.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "essence.itemDesc",
    },
    spartans: {
        key: Item_Enums_1.EItemKey.spartans,
        image: "Res/items/spartans.jpg",
        name: "spartans.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "spartans.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "spartans.itemDesc",
    },
    spartans_pack: {
        key: Item_Enums_1.EItemKey.spartans_pack,
        image: "Res/items/spartansPack.jpg",
        name: "spartans.pack.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "spartans.pack.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "spartans.pack.itemDesc",
    },
    spartans_relics: {
        key: Item_Enums_1.EItemKey.spartans_relics,
        image: "Res/items/spartansRelics.jpg",
        name: "spartans.relics.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "spartans.relics.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "spartans.relics.itemDesc",
    },
    spartans_flag_pack: {
        key: Item_Enums_1.EItemKey.spartans_flag_pack,
        image: "Res/items/MacedonEmblemPack.jpg",
        name: "MacedonEmblemPack.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "MacedonEmblemPack.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "MacedonEmblemPack.itemDesc",
    },
    thor_stone: {
        key: Item_Enums_1.EItemKey.thor_stone,
        image: "Res/items/ThorStone.jpg",
        name: "ThorStone.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "ThorStone.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "ThorStone.itemDesc",
    },
    thor_pergament: {
        key: Item_Enums_1.EItemKey.thor_pergament,
        image: "Res/items/ThorPergament.jpg",
        name: "ThorPergament.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "ThorPergament.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "ThorPergament.itemDesc",
    },
    thor_fragment: {
        key: Item_Enums_1.EItemKey.thor_fragment,
        image: "Res/items/ThorFragment.jpg",
        name: "ThorFragment.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "ThorFragment.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "ThorFragment.itemDesc",
    },
    sol_stone: {
        key: Item_Enums_1.EItemKey.sol_stone,
        image: "Res/items/SolStone.jpg",
        name: "SolStone.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "SolStone.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "SolStone.itemDesc",
    },
    sol_pergament: {
        key: Item_Enums_1.EItemKey.sol_pergament,
        image: "Res/items/SolPergament.jpg",
        name: "SolPergament.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "SolPergament.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "SolPergament.itemDesc",
    },
    sol_fragment: {
        key: Item_Enums_1.EItemKey.sol_fragment,
        image: "Res/items/SolFragment.jpg",
        name: "SolFragment.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "SolFragment.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "SolFragment.itemDesc",
    },
    luna_stone: {
        key: Item_Enums_1.EItemKey.luna_stone,
        image: "Res/items/LunaStone.jpg",
        name: "LunaStone.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "LunaStone.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "LunaStone.itemDesc",
    },
    luna_pergament: {
        key: Item_Enums_1.EItemKey.luna_pergament,
        image: "Res/items/LunaPergament.jpg",
        name: "LunaPergament.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "LunaPergament.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "LunaPergament.itemDesc",
    },
    luna_fragment: {
        key: Item_Enums_1.EItemKey.luna_fragment,
        image: "Res/items/LunaFragment.jpg",
        name: "LunaFragment.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "LunaFragment.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "LunaFragment.itemDesc",
    },
    hephaestus_pieces_a: {
        key: Item_Enums_1.EItemKey.hephaestus_pieces_a,
        image: "Res/items/HephaestusPiecesA.jpg",
        name: "HephaestusPiecesA.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        startingAmount: 1,
        desc: "HephaestusPiecesA.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "HephaestusPiecesA.itemDesc",
    },
    hephaestus_pieces_b: {
        key: Item_Enums_1.EItemKey.hephaestus_pieces_b,
        image: "Res/items/HephaestusPiecesB.jpg",
        name: "HephaestusPiecesB.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        startingAmount: 1,
        desc: "HephaestusPiecesB.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "HephaestusPiecesB.itemDesc",
    },
    mars_pieces_a: {
        key: Item_Enums_1.EItemKey.mars_pieces_a,
        image: "Res/items/MarspiecesA.jpg",
        name: "MarsPiecesA.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        startingAmount: 1,
        desc: "MarsPiecesA.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "MarsPiecesA.itemDesc",
    },
    mars_pieces_b: {
        key: Item_Enums_1.EItemKey.mars_pieces_b,
        image: "Res/items/MarspiecesB.jpg",
        name: "MarsPiecesB.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        startingAmount: 1,
        desc: "MarsPiecesB.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "MarsPiecesB.itemDesc",
    },
    mars_pieces_c: {
        key: Item_Enums_1.EItemKey.mars_pieces_c,
        image: "Res/items/Static/MarspiecesC.jpg",
        name: "MarspiecesC.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        startingAmount: 1,
        desc: "MarspiecesC.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "MarspiecesC.itemDesc",
    },
    soul_of_mars: {
        key: Item_Enums_1.EItemKey.soul_of_mars,
        image: "Res/items/SoulofMars.jpg",
        name: "SoulofMars.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        startingAmount: 1,
        desc: "SoulofMars.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "SoulofMars.itemDesc",
    },
    gold_bar_piece: {
        key: Item_Enums_1.EItemKey.gold_bar_piece,
        image: "Res/items/GoldBarPiece.jpg",
        name: "GoldBarPiece.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 40,
        desc: "GoldBarPiece.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "GoldBarPiece.itemDesc",
    },
    gold_bar: {
        key: Item_Enums_1.EItemKey.gold_bar,
        image: "Res/items/GoldBar.jpg",
        name: "GoldBar.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "GoldBar.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "GoldBar.itemDesc",
    },
    silver_bar: {
        key: Item_Enums_1.EItemKey.silver_bar,
        image: "Res/items/SilverBar.jpg",
        name: "SilverBar.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "SilverBar.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "SilverBar.itemDesc",
    },
    silver_bar_pack: {
        key: Item_Enums_1.EItemKey.silver_bar_pack,
        image: "Res/items/BigSilverBarPack.jpg",
        name: "BigSilverBarPack.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "BigSilverBarPack.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "BigSilverBarPack.itemDesc",
    },
    gold_bar_pack: {
        key: Item_Enums_1.EItemKey.gold_bar_pack,
        image: "Res/items/GoldBarPack.jpg",
        name: "GoldBarPack.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "GoldBarPack.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "GoldBarPack.itemDesc",
    },
    high_glory_stone: {
        key: Item_Enums_1.EItemKey.high_glory_stone,
        image: "Res/items/HighGloryStone.jpg",
        name: "HighGloryStone.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 40,
        desc: "HighGloryStone.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "HighGloryStone.itemDesc",
    },
    medium_glory_stone: {
        key: Item_Enums_1.EItemKey.medium_glory_stone,
        image: "Res/items/MediumGloryStone.jpg",
        name: "MediumGloryStone.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 25,
        desc: "MediumGloryStone.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "MediumGloryStone.itemDesc",
    },
    low_glory_stone: {
        key: Item_Enums_1.EItemKey.low_glory_stone,
        image: "Res/items/LowGloryStone.jpg",
        name: "LowGloryStone.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 10,
        desc: "LowGloryStone.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "LowGloryStone.itemDesc",
    },
    archit_a: {
        image: "Res/items/item028.jpg",
        key: Item_Enums_1.EItemKey.archit_a,
        name: "speedup15mBuilding.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5,
        desc: "speedup15mBuilding.funcDesc",
        startingAmount: 2,
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup15mBuilding.itemDesc",
    },
    archit_b: {
        image: "Res/items/item029.jpg",
        key: Item_Enums_1.EItemKey.archit_b,
        name: "speedup1hBuilding.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 10,
        desc: "speedup1hBuilding.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup1hBuilding.itemDesc",
    },
    archit_c: {
        image: "Res/items/item030.jpg",
        key: Item_Enums_1.EItemKey.archit_c,
        name: "speedup3hBuilding.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 20,
        desc: "speedup3hBuilding.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup3hBuilding.itemDesc",
    },
    archit_d: {
        image: "Res/items/item031.jpg",
        key: Item_Enums_1.EItemKey.archit_d,
        name: "speedup30pBuilding.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "speedup30pBuilding.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup30pBuilding.itemDesc",
    },
    archim_a: {
        image: "Res/items/item032.jpg",
        key: Item_Enums_1.EItemKey.archim_a,
        name: "speedup15mTech.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5,
        desc: "speedup15mTech.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup15mTech.itemDesc",
    },
    archim_b: {
        image: "Res/items/item033.jpg",
        key: Item_Enums_1.EItemKey.archim_b,
        name: "speedup1hTech.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 10,
        desc: "speedup1hTech.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup1hTech.itemDesc",
    },
    archim_c: {
        image: "Res/items/item034.jpg",
        key: Item_Enums_1.EItemKey.archim_c,
        name: "speedup3hTech.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 20,
        desc: "speedup3hTech.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup3hTech.itemDesc",
    },
    archim_d: {
        image: "Res/items/item035.jpg",
        key: Item_Enums_1.EItemKey.archim_d,
        name: "speedup30pTech.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "speedup30pTech.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup30pTech.itemDesc",
    },
    polit_a: {
        image: "Res/items/item099.jpg",
        key: Item_Enums_1.EItemKey.polit_a,
        name: "speedup15mTraining.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5,
        desc: "speedup15mTraining.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup15mTraining.itemDesc",
    },
    polit_b: {
        image: "Res/items/item100.jpg",
        key: Item_Enums_1.EItemKey.polit_b,
        name: "speedup1hTraining.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 10,
        desc: "speedup1hTraining.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup1hTraining.itemDesc",
    },
    polit_c: {
        image: "Res/items/item101.jpg",
        key: Item_Enums_1.EItemKey.polit_c,
        name: "speedup3hTraining.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 20,
        desc: "speedup3hTraining.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup3hTraining.itemDesc",
    },
    polit_d: {
        image: "Res/items/item102.jpg",
        key: Item_Enums_1.EItemKey.polit_d,
        name: "speedup30pTraining.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "speedup30pTraining.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup30pTraining.itemDesc",
    },
    bread: {
        image: "Res/items/item091.jpg",
        key: Item_Enums_1.EItemKey.bread,
        name: "bread.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 10,
        startingAmount: 5,
        desc: "bread.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "bread.itemDesc",
    },
    fruit: {
        image: "Res/items/item092.jpg",
        key: Item_Enums_1.EItemKey.fruit,
        name: "fruit.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 30,
        desc: "fruit.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "fruit.itemDesc",
    },
    milk: {
        image: "Res/items/item093.jpg",
        key: Item_Enums_1.EItemKey.milk,
        name: "milk.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 60,
        desc: "milk.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "milk.itemDesc",
    },
    meat: {
        image: "Res/items/item094.jpg",
        key: Item_Enums_1.EItemKey.meat,
        name: "meat.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 80,
        desc: "meat.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "meat.itemDesc",
    },
    coach: {
        image: "Res/items/item088.jpg",
        key: Item_Enums_1.EItemKey.coach,
        name: "speedupWorkout.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5,
        desc: "speedupWorkout.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedupWorkout.itemDesc",
    },
    train_acce_30: {
        image: "Res/items/item036.jpg",
        key: Item_Enums_1.EItemKey.train_acce_30,
        name: "speedupTroop.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 40,
        desc: "speedupTroop.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedupTroop.itemDesc",
    },
    train_acce_50: {
        image: "Res/items/item152.jpg",
        key: Item_Enums_1.EItemKey.train_acce_50,
        name: "speedup50pTroop.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 60,
        desc: "speedup50pTroop.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedup50pTroop.itemDesc",
    },
    exp_hero_8: {
        image: "Res/items/item040.jpg",
        key: Item_Enums_1.EItemKey.exp_hero_8,
        name: "incHeroExp1k.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 8,
        startingAmount: 1,
        desc: "incHeroExp1k.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "incHeroExp1k.itemDesc",
    },
    exp_hero_30: {
        image: "Res/items/item041.jpg",
        key: Item_Enums_1.EItemKey.exp_hero_30,
        name: "incHeroExp10k.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 30,
        desc: "incHeroExp10k.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "incHeroExp10k.itemDesc",
    },
    exp_hero_100: {
        image: "Res/items/item042.jpg",
        key: Item_Enums_1.EItemKey.exp_hero_100,
        name: "incHeroExp100k.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "incHeroExp100k.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "incHeroExp100k.itemDesc",
    },
    hero_back: {
        image: "Res/items/item087.jpg",
        key: Item_Enums_1.EItemKey.hero_back,
        name: "comeBack.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5,
        desc: "comeBack.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "comeBack.itemDesc",
    },
    shopping_car: {
        image: "Res/items/item038.jpg",
        key: Item_Enums_1.EItemKey.shopping_car,
        name: "speedupTrade.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5,
        desc: "speedupTrade.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedupTrade.itemDesc",
    },
    wall_acce: {
        image: "Res/items/item037.jpg",
        key: Item_Enums_1.EItemKey.wall_acce,
        name: "speedupFort.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 50,
        desc: "speedupFort.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "speedupFort.itemDesc",
    },
    powder_keg: {
        image: "Res/items/item039.jpg",
        key: Item_Enums_1.EItemKey.powder_keg,
        name: "destoryBuilding.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 20,
        desc: "destoryBuilding.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_acce,
        db_tab: Item_Enums_1.EItemTable.matrial_acce,
        long_desc: "destoryBuilding.itemDesc",
    },
    wheat_1: {
        image: "Res/items/item043.jpg",
        key: Item_Enums_1.EItemKey.wheat_1,
        name: "incResFood1D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 8,
        desc: "incResFood1D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_product,
        db_tab: Item_Enums_1.EItemTable.matrial_product,
        long_desc: "incResFood1D.itemDesc",
    },
    wheat_7: {
        image: "Res/items/item044.jpg",
        key: Item_Enums_1.EItemKey.wheat_7,
        name: "incResFood7D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 50,
        desc: "incResFood7D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_product,
        db_tab: Item_Enums_1.EItemTable.matrial_product,
        long_desc: "incResFood7D.itemDesc",
    },
    stone_1: {
        image: "Res/items/item047.jpg",
        key: Item_Enums_1.EItemKey.stone_1,
        name: "incResStone1D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 8,
        desc: "incResStone1D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_product,
        db_tab: Item_Enums_1.EItemTable.matrial_product,
        long_desc: "incResStone1D.itemDesc",
    },
    stone_7: {
        image: "Res/items/item048.jpg",
        key: Item_Enums_1.EItemKey.stone_7,
        name: "incResStone7D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 50,
        desc: "incResStone7D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_product,
        db_tab: Item_Enums_1.EItemTable.matrial_product,
        long_desc: "incResStone7D.itemDesc",
    },
    wood_1: {
        image: "Res/items/item045.jpg",
        key: Item_Enums_1.EItemKey.wood_1,
        name: "incResWood1D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 8,
        desc: "incResWood1D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_product,
        db_tab: Item_Enums_1.EItemTable.matrial_product,
        long_desc: "incResWood1D.itemDesc",
    },
    wood_7: {
        image: "Res/items/item046.jpg",
        key: Item_Enums_1.EItemKey.wood_7,
        name: "incResWood7D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 50,
        desc: "incResWood7D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_product,
        db_tab: Item_Enums_1.EItemTable.matrial_product,
        long_desc: "incResWood7D.itemDesc",
    },
    metal_1: {
        image: "Res/items/item049.jpg",
        key: Item_Enums_1.EItemKey.metal_1,
        name: "incResIron1D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 8,
        desc: "incResIron1D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_product,
        db_tab: Item_Enums_1.EItemTable.matrial_product,
        long_desc: "incResIron1D.itemDesc",
    },
    metal_7: {
        image: "Res/items/item050.jpg",
        key: Item_Enums_1.EItemKey.metal_7,
        name: "incResIron7D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 50,
        desc: "incResIron7D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_product,
        db_tab: Item_Enums_1.EItemTable.matrial_product,
        long_desc: "incResIron7D.itemDesc",
    },
    coin_1: {
        image: "Res/items/item051.jpg",
        key: Item_Enums_1.EItemKey.coin_1,
        name: "incResGold1D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 45,
        desc: "incResGold1D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_product,
        db_tab: Item_Enums_1.EItemTable.matrial_product,
        long_desc: "incResGold1D.itemDesc",
    },
    coin_7: {
        image: "Res/items/item052.jpg",
        key: Item_Enums_1.EItemKey.coin_7,
        name: "incResGold7D.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 280,
        desc: "incResGold7D.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_product,
        db_tab: Item_Enums_1.EItemTable.matrial_product,
        long_desc: "incResGold7D.itemDesc",
    },
    gift_box: {
        image: "Res/items/item068.jpg",
        key: Item_Enums_1.EItemKey.gift_box,
        name: "player.box.special.1.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 80,
        desc: "player.box.special.1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.special.1.itemDesc",
    },
    wood_box: {
        image: "Res/items/item069.jpg",
        key: Item_Enums_1.EItemKey.wood_box,
        name: "player.box.special.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 140,
        desc: "player.box.special.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.special.2.itemDesc",
    },
    golden_box: {
        image: "Res/items/item070.jpg",
        key: Item_Enums_1.EItemKey.golden_box,
        name: "player.box.special.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 240,
        desc: "player.box.special.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.special.3.itemDesc",
    },
    luck_play: {
        image: "Res/items/item098.jpg",
        key: Item_Enums_1.EItemKey.luck_play,
        name: "sacrifice.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "sacrifice.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "sacrifice.itemDesc",
    },
    hidden_box: {
        key: Item_Enums_1.EItemKey.hidden_box,
        image: "Res/items/Hiddenbox.jpg",
        name: "Hiddenbox.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "Hiddenbox.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Hiddenbox.itemDesc",
    },
    army_box: {
        image: "Res/items/item066.jpg",
        key: Item_Enums_1.EItemKey.army_box,
        name: "player.box.troop.1.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 400,
        desc: "player.box.troop.1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.troop.1.itemDesc",
    },
    tagned_3p: {
        image: "Res/items/item086.jpg",
        key: Item_Enums_1.EItemKey.tagned_3p,
        name: "player.box.gambling.hero.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 50,
        startingAmount: 1,
        desc: "player.box.gambling.hero.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.gambling.hero.3.itemDesc",
    },
    tagned_4p: {
        image: "Res/items/item086.jpg",
        key: Item_Enums_1.EItemKey.tagned_4p,
        name: "player.box.gambling.hero.4.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 80,
        desc: "player.box.gambling.hero.4.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.gambling.hero.4.itemDesc",
    },
    tagned_5p: {
        image: "Res/items/item086.jpg",
        key: Item_Enums_1.EItemKey.tagned_5p,
        name: "player.box.gambling.hero.5.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 120,
        desc: "player.box.gambling.hero.5.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.gambling.hero.5.itemDesc",
    },
    tagned_6p: {
        image: "Res/items/item086.jpg",
        key: Item_Enums_1.EItemKey.tagned_6p,
        name: "player.box.gambling.hero.6.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "player.box.gambling.hero.6.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.gambling.hero.6.itemDesc",
    },
    tagned_7p: {
        image: "Res/items/item086.jpg",
        key: Item_Enums_1.EItemKey.tagned_7p,
        name: "player.box.gambling.hero.7.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "player.box.gambling.hero.7.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.gambling.hero.7.itemDesc",
    },
    tagned_8p: {
        image: "Res/items/item086.jpg",
        key: Item_Enums_1.EItemKey.tagned_8p,
        name: "player.box.gambling.hero.8.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "player.box.gambling.hero.8.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.gambling.hero.8.itemDesc",
    },
    easter_pack: {
        key: Item_Enums_1.EItemKey.easter_pack,
        image: "Res/items/EasterFortune.jpg",
        name: "EasterFortune.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "EasterFortune.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "EasterFortune.itemDesc",
    },
    easter_box: {
        key: Item_Enums_1.EItemKey.easter_box,
        image: "Res/items/EasterPack.jpg",
        name: "EasterPack.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "EasterPack.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "EasterPack.itemDesc",
    },
    necklace_1: {
        image: "Res/items/item159.jpg",
        key: Item_Enums_1.EItemKey.necklace_1,
        name: "Trans.gray.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 10,
        desc: "Trans.gray.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Trans.gray.itemDesc",
    },
    necklace_2: {
        image: "Res/items/item157.jpg",
        key: Item_Enums_1.EItemKey.necklace_2,
        name: "Trans.yellow.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 20,
        desc: "Trans.yellow.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Trans.yellow.itemDesc",
    },
    necklace_3: {
        image: "Res/items/item158.jpg",
        key: Item_Enums_1.EItemKey.necklace_3,
        name: "Trans.purple.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 30,
        desc: "Trans.purple.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Trans.purple.itemDesc",
    },
    necklace_4: {
        image: "Res/items/item162.jpg",
        key: Item_Enums_1.EItemKey.necklace_4,
        name: "Trans.black.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 30,
        desc: "Trans.black.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Trans.black.itemDesc",
    },
    necklace_5: {
        image: "Res/items/item160.jpg",
        key: Item_Enums_1.EItemKey.necklace_5,
        name: "Trans.red.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "Trans.red.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Trans.red.itemDesc",
    },
    necklace_6: {
        image: "Res/items/item161.jpg",
        key: Item_Enums_1.EItemKey.necklace_6,
        name: "القلادة الخضراء",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 150,
        desc: "",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "",
    },
    medal_bronz: {
        image: "Res/items/CopperMedal.jpg",
        key: Item_Enums_1.EItemKey.medal_bronz,
        name: "CopperMedal.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 2,
        desc: "CopperMedal.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "CopperMedal.itemDesc",
    },
    medal_silver: {
        image: "Res/items/SilverMedal.jpg",
        key: Item_Enums_1.EItemKey.medal_silver,
        name: "SilverMedal.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 5,
        desc: "SilverMedal.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "SilverMedal.itemDesc",
    },
    medal_gold: {
        image: "Res/items/GoldMedal.jpg",
        key: Item_Enums_1.EItemKey.medal_gold,
        name: "GoldMedal.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 8,
        desc: "GoldMedal.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "GoldMedal.itemDesc",
    },
    army_all_1: {
        image: "Res/items/item143.jpg",
        key: Item_Enums_1.EItemKey.army_all_1,
        name: "Battalion.1.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "Battalion.1.funcDesc",
        startingAmount: 3,
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Battalion.1.itemDesc",
    },
    army_all_2: {
        image: "Res/items/item144.jpg",
        key: Item_Enums_1.EItemKey.army_all_2,
        name: "Battalion.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "Battalion.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Battalion.2.itemDesc",
    },
    army_all_3: {
        image: "Res/items/item145.jpg",
        key: Item_Enums_1.EItemKey.army_all_3,
        name: "Battalion.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "Battalion.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Battalion.3.itemDesc",
    },
    army_all_pack: {
        key: Item_Enums_1.EItemKey.army_all_pack,
        image: "Res/items/PowerfulMilitaryPack.jpg",
        name: "PowerfulMilitaryPack.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "PowerfulMilitaryPack.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "PowerfulMilitaryPack.itemDesc",
    },
    army_a_100: {
        image: "Res/items/item144.jpg",
        key: Item_Enums_1.EItemKey.army_a_100,
        name: "Triarii.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "Triarii.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Triarii.2.itemDesc",
    },
    army_b_100: {
        image: "Res/items/item144.jpg",
        key: Item_Enums_1.EItemKey.army_b_100,
        name: "Cavalry.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "Cavalry.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Cavalry.2.itemDesc",
    },
    army_c_100: {
        image: "Res/items/item144.jpg",
        key: Item_Enums_1.EItemKey.army_c_100,
        name: "UrbanCohort.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "UrbanCohort.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "UrbanCohort.2.itemDesc",
    },
    army_d_100: {
        image: "Res/items/item144.jpg",
        key: Item_Enums_1.EItemKey.army_d_100,
        name: "Archers.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "Archers.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Archers.2.itemDesc",
    },
    army_e_100: {
        image: "Res/items/item144.jpg",
        key: Item_Enums_1.EItemKey.army_e_100,
        name: "Ballista.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "Ballista.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Ballista.2.itemDesc",
    },
    army_f_100: {
        image: "Res/items/item144.jpg",
        key: Item_Enums_1.EItemKey.army_f_100,
        name: "Onagers.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "Onagers.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Onagers.2.itemDesc",
    },
    army_a_1000: {
        image: "Res/items/item145.jpg",
        key: Item_Enums_1.EItemKey.army_a_1000,
        name: "Triarii.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 5,
        desc: "Triarii.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Triarii.3.itemDesc",
    },
    army_b_1000: {
        image: "Res/items/item145.jpg",
        key: Item_Enums_1.EItemKey.army_b_1000,
        name: "Cavalry.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 15,
        desc: "Cavalry.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Cavalry.3.itemDesc",
    },
    army_c_1000: {
        image: "Res/items/item145.jpg",
        key: Item_Enums_1.EItemKey.army_c_1000,
        name: "UrbanCohort.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 35,
        desc: "UrbanCohort.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "UrbanCohort.3.itemDesc",
    },
    army_d_1000: {
        image: "Res/items/item145.jpg",
        key: Item_Enums_1.EItemKey.army_d_1000,
        name: "Archers.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 10,
        desc: "Archers.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Archers.3.itemDesc",
    },
    army_e_1000: {
        image: "Res/items/item145.jpg",
        key: Item_Enums_1.EItemKey.army_e_1000,
        name: "Ballista.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 25,
        desc: "Ballista.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Ballista.3.itemDesc",
    },
    army_f_1000: {
        image: "Res/items/item145.jpg",
        key: Item_Enums_1.EItemKey.army_f_1000,
        name: "Onagers.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 40,
        desc: "Onagers.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Onagers.3.itemDesc",
    },
    t_map: {
        image: "Res/items/item090.jpg",
        key: Item_Enums_1.EItemKey.t_map,
        name: "treasureMap.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "treasureMap.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "treasureMap.itemDesc",
    },
    food_a: {
        image: "Res/items/item081.jpg",
        key: Item_Enums_1.EItemKey.food_a,
        name: "_number.player.box.gambling.food.40000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 3,
        desc: "_number.player.box.gambling.food.40000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.food.40000.itemDesc",
    },
    food_b: {
        image: "Res/items/item081.jpg",
        key: Item_Enums_1.EItemKey.food_b,
        name: "_number.player.box.gambling.food.80000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 10,
        desc: "_number.player.box.gambling.food.80000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.food.80000.itemDesc",
    },
    food_c: {
        image: "Res/items/item081.jpg",
        key: Item_Enums_1.EItemKey.food_c,
        name: "_number.player.box.gambling.food.120000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 2,
        startingAmount: 1,
        desc: "_number.player.box.gambling.food.120000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.food.120000.itemDesc",
    },
    food_d: {
        image: "Res/items/item081.jpg",
        key: Item_Enums_1.EItemKey.food_d,
        name: "_number.player.box.gambling.food.160000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 5,
        desc: "_number.player.box.gambling.food.160000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.food.160000.itemDesc",
    },
    wood_a: {
        image: "Res/items/item082.jpg",
        key: Item_Enums_1.EItemKey.wood_a,
        name: "_number.player.box.gambling.wood.40000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 3,
        desc: "_number.player.box.gambling.wood.40000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.wood.40000.itemDesc",
    },
    wood_b: {
        image: "Res/items/item082.jpg",
        key: Item_Enums_1.EItemKey.wood_b,
        name: "_number.player.box.gambling.wood.80000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 10,
        desc: "_number.player.box.gambling.wood.80000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.wood.80000.itemDesc",
    },
    wood_c: {
        image: "Res/items/item082.jpg",
        key: Item_Enums_1.EItemKey.wood_c,
        name: "_number.player.box.gambling.wood.120000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 50,
        startingAmount: 1,
        desc: "_number.player.box.gambling.wood.120000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.wood.120000.itemDesc",
    },
    wood_d: {
        image: "Res/items/item082.jpg",
        key: Item_Enums_1.EItemKey.wood_d,
        name: "_number.player.box.gambling.wood.160000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 100,
        desc: "_number.player.box.gambling.wood.160000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.wood.160000.itemDesc",
    },
    stone_a: {
        image: "Res/items/item083.jpg",
        key: Item_Enums_1.EItemKey.stone_a,
        name: "_number.player.box.gambling.stone.40000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 3,
        desc: "_number.player.box.gambling.stone.40000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.stone.40000.itemDesc",
    },
    stone_b: {
        image: "Res/items/item083.jpg",
        key: Item_Enums_1.EItemKey.stone_b,
        name: "_number.player.box.gambling.stone.80000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 10,
        desc: "_number.player.box.gambling.stone.80000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.stone.80000.itemDesc",
    },
    stone_c: {
        image: "Res/items/item083.jpg",
        key: Item_Enums_1.EItemKey.stone_c,
        name: "_number.player.box.gambling.stone.120000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 50,
        startingAmount: 1,
        desc: "_number.player.box.gambling.stone.120000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.stone.120000.itemDesc",
    },
    stone_d: {
        image: "Res/items/item083.jpg",
        key: Item_Enums_1.EItemKey.stone_d,
        name: "_number.player.box.gambling.stone.160000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 100,
        desc: "_number.player.box.gambling.stone.160000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.stone.160000.itemDesc",
    },
    metal_a: {
        image: "Res/items/item084.jpg",
        key: Item_Enums_1.EItemKey.metal_a,
        name: "_number.player.box.gambling.iron.40000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 3,
        desc: "_number.player.box.gambling.iron.40000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.iron.40000.itemDesc",
    },
    metal_b: {
        image: "Res/items/item084.jpg",
        key: Item_Enums_1.EItemKey.metal_b,
        name: "_number.player.box.gambling.iron.80000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 10,
        desc: "_number.player.box.gambling.iron.80000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.iron.80000.itemDesc",
    },
    metal_c: {
        image: "Res/items/item084.jpg",
        key: Item_Enums_1.EItemKey.metal_c,
        name: "_number.player.box.gambling.iron.120000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 50,
        desc: "_number.player.box.gambling.iron.120000.funcDesc",
        startingAmount: 1,
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.iron.120000.itemDesc",
    },
    metal_d: {
        image: "Res/items/item084.jpg",
        key: Item_Enums_1.EItemKey.metal_d,
        name: "_number.player.box.gambling.iron.160000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 100,
        desc: "_number.player.box.gambling.iron.160000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.iron.160000.itemDesc",
    },
    coin_a: {
        image: "Res/items/item085.jpg",
        key: Item_Enums_1.EItemKey.coin_a,
        name: "_number.player.box.gambling.gold.20000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 3,
        desc: "_number.player.box.gambling.gold.20000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.gold.20000.itemDesc",
    },
    coin_b: {
        image: "Res/items/item085.jpg",
        key: Item_Enums_1.EItemKey.coin_b,
        name: "_number.player.box.gambling.gold.40000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 10,
        desc: "_number.player.box.gambling.gold.40000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.gold.40000.itemDesc",
    },
    coin_c: {
        image: "Res/items/item085.jpg",
        key: Item_Enums_1.EItemKey.coin_c,
        name: "_number.player.box.gambling.gold.60000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 50,
        desc: "_number.player.box.gambling.gold.60000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.gold.60000.itemDesc",
    },
    coin_d: {
        image: "Res/items/item085.jpg",
        key: Item_Enums_1.EItemKey.coin_d,
        name: "_number.player.box.gambling.gold.60000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 100,
        desc: "_number.player.box.gambling.gold.60000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "_number.player.box.gambling.gold.60000.itemDesc",
    },
    beginner_back_1: {
        image: "Res/items/item053.jpg",
        key: Item_Enums_1.EItemKey.beginner_back_1,
        name: "player.box.present.1.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 0,
        desc: "player.box.present.1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.present.1.itemDesc",
    },
    beginner_back_2: {
        image: "Res/items/item054.jpg",
        key: Item_Enums_1.EItemKey.beginner_back_2,
        name: "player.box.present.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 0,
        desc: "player.box.present.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.present.2.itemDesc",
    },
    beginner_back_3: {
        image: "Res/items/item055.jpg",
        key: Item_Enums_1.EItemKey.beginner_back_3,
        name: "player.box.present.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 0,
        desc: "player.box.present.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.present.3.itemDesc",
    },
    beginner_back_4: {
        image: "Res/items/item056.jpg",
        key: Item_Enums_1.EItemKey.beginner_back_4,
        name: "player.box.present.4.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 0,
        desc: "player.box.present.4.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.present.4.itemDesc",
    },
    beginner_back_5: {
        image: "Res/items/item057.jpg",
        key: Item_Enums_1.EItemKey.beginner_back_5,
        name: "player.box.present.5.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 0,
        desc: "player.box.present.5.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.present.5.itemDesc",
    },
    beginner_back_6: {
        key: Item_Enums_1.EItemKey.beginner_back_6,
        image: "Res/items/item058.jpg",
        name: "player.box.present.6.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "player.box.present.6.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.present.6.itemDesc",
    },
    beginner_back_7: {
        key: Item_Enums_1.EItemKey.beginner_back_7,
        image: "Res/items/item059.jpg",
        name: "player.box.present.7.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "player.box.present.7.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.present.7.itemDesc",
    },
    beginner_back_8: {
        key: Item_Enums_1.EItemKey.beginner_back_8,
        image: "Res/items/item060.jpg",
        name: "player.box.present.8.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "player.box.present.8.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.present.8.itemDesc",
    },
    beginner_back_9: {
        key: Item_Enums_1.EItemKey.beginner_back_9,
        image: "Res/items/item061.jpg",
        name: "player.box.present.9.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "player.box.present.9.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.present.9.itemDesc",
    },
    beginner_back_10: {
        key: Item_Enums_1.EItemKey.beginner_back_10,
        image: "Res/items/item062.jpg",
        name: "player.box.present.10.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "player.box.present.10.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "player.box.present.10.itemDesc",
    },
    king_chest: {
        key: Item_Enums_1.EItemKey.king_chest,
        image: "Res/items/kingChest.jpg",
        name: "kingChest.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "kingChest.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "kingChest.itemDesc",
    },
    roma_colosseum_box: {
        key: Item_Enums_1.EItemKey.roma_colosseum_box,
        image: "Res/items/RomaColosseumbox.jpg",
        name: "RomaColosseumbox.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "RomaColosseumbox.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "RomaColosseumbox.itemDesc",
        prizeLimit: 1,
    },
    senate_golden_box: {
        key: Item_Enums_1.EItemKey.senate_golden_box,
        image: "Res/items/SenateGoldenBox.jpg",
        name: "SenateGoldenBox.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "SenateGoldenBox.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "SenateGoldenBox.itemDesc",
        prizeLimit: 1,
    },
    senate_platinum_box: {
        key: Item_Enums_1.EItemKey.senate_platinum_box,
        image: "Res/items/SenatePlatinumBox.jpg",
        name: "SenatePlatinumBox.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "SenatePlatinumBox.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "SenatePlatinumBox.itemDesc",
        prizeLimit: 1,
    },
    senate_silver_box: {
        key: Item_Enums_1.EItemKey.senate_silver_box,
        image: "Res/items/SenateSilverBox.jpg",
        name: "SenateSilverBox.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "SenateSilverBox.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "SenateSilverBox.itemDesc",
        prizeLimit: 1,
    },
    platinum_supply_crate: {
        key: Item_Enums_1.EItemKey.platinum_supply_crate,
        image: "Res/items/PlatinumSupplyCrate.jpg",
        name: "PlatinumSupplyCrate.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "PlatinumSupplyCrate.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "PlatinumSupplyCrate.itemDesc",
        prizeLimit: 1,
    },
    diamond_supply_crate: {
        key: Item_Enums_1.EItemKey.diamond_supply_crate,
        image: "Res/items/DiamondSupplyCrate.jpg",
        name: "DiamondSupplyCrate.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "DiamondSupplyCrate.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "DiamondSupplyCrate.itemDesc",
    },
    golden_supply_crate: {
        key: Item_Enums_1.EItemKey.golden_supply_crate,
        image: "Res/items/GoldenSupplyCrate.jpg",
        name: "GoldenSupplyCrate.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "GoldenSupplyCrate.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "GoldenSupplyCrate.itemDesc",
    },
    silver_supply_crate: {
        key: Item_Enums_1.EItemKey.silver_supply_crate,
        image: "Res/items/SilverSupplyCrate.jpg",
        name: "SilverSupplyCrate.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "SilverSupplyCrate.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "SilverSupplyCrate.itemDesc",
    },
    sol_chest: {
        key: Item_Enums_1.EItemKey.sol_chest,
        image: "Res/items/SolChest.jpg",
        name: "SolChest.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "SolChest.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "SolChest.itemDesc",
    },
    MinervaGem: {
        key: Item_Enums_1.EItemKey.MinervaGem,
        image: "Res/items/MinervaGem.jpg",
        name: "MinervaGem.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "MinervaGem.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "MinervaGem.itemDesc",
    },
    MinervaHolyWater: {
        key: Item_Enums_1.EItemKey.MinervaHolyWater,
        image: "Res/items/MinervaHolyWater.jpg",
        name: "MinervaHolyWater.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "MinervaHolyWater.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "MinervaHolyWater.itemDesc",
    },
    MinervaHolyWater_S: {
        key: Item_Enums_1.EItemKey.MinervaHolyWater_S,
        image: "Res/items/MinervaHolyWater.S.jpg",
        name: "MinervaHolyWater.S.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "MinervaHolyWater.S.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "MinervaHolyWater.S.itemDesc",
    },
    MinervaHolyWater_M: {
        key: Item_Enums_1.EItemKey.MinervaHolyWater_M,
        image: "Res/items/MinervaHolyWater.M.jpg",
        name: "MinervaHolyWater.M.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "MinervaHolyWater.M.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "MinervaHolyWater.M.itemDesc",
    },
    MinervaHolyWater_L: {
        key: Item_Enums_1.EItemKey.MinervaHolyWater_L,
        image: "Res/items/MinervaHolyWater.L.jpg",
        name: "MinervaHolyWater.L.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "MinervaHolyWater.L.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "MinervaHolyWater.L.itemDesc",
    },
    MinervaHolyWaterPack: {
        key: Item_Enums_1.EItemKey.MinervaHolyWaterPack,
        image: "Res/items/MinervaHoyWaterIII.jpg",
        name: "MinervaHoyWaterIII.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "MinervaHoyWaterIII.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "MinervaHoyWaterIII.itemDesc",
    },
    vertumnus_box: {
        key: Item_Enums_1.EItemKey.vertumnus_box,
        image: "Res/items/Vertumnusbox.jpg",
        name: "Vertumnusbox.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "Vertumnusbox.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "Vertumnusbox.itemDesc",
    },
    winter_pack: {
        key: Item_Enums_1.EItemKey.winter_pack,
        image: "Res/items/WinterPack.jpg",
        name: "WinterPack.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "WinterPack.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "WinterPack.itemDesc",
    },
    lost_pack: {
        key: Item_Enums_1.EItemKey.lost_pack,
        image: "Res/items/LostPackage.jpg",
        name: "LostPackage.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LostPackage.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LostPackage.itemDesc",
    },
    letter_pendant_up_2: {
        key: Item_Enums_1.EItemKey.letter_pendant_up_2,
        image: "Res/items/LoyalAccessoriesLetterOfUpgradeResult.Lv2.jpg",
        name: "LoyalAccessoriesLetterOfUpgradeResult.Lv2.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalAccessoriesLetterOfUpgradeResult.Lv2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalAccessoriesLetterOfUpgradeResult.Lv2.itemDesc",
    },
    letter_pendant_up_3: {
        key: Item_Enums_1.EItemKey.letter_pendant_up_3,
        image: "Res/items/LoyalAccessoriesLetterOfUpgradeResult.Lv3.jpg",
        name: "LoyalAccessoriesLetterOfUpgradeResult.Lv3.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalAccessoriesLetterOfUpgradeResult.Lv3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalAccessoriesLetterOfUpgradeResult.Lv3.itemDesc",
    },
    letter_belt_up_2: {
        key: Item_Enums_1.EItemKey.letter_belt_up_2,
        image: "Res/items/LoyalCaestusLetterOfUpgradeResult.Lv2.jpg",
        name: "LoyalCaestusLetterOfUpgradeResult.Lv2.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalCaestusLetterOfUpgradeResult.Lv2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalCaestusLetterOfUpgradeResult.Lv2.itemDesc",
    },
    letter_belt_up_3: {
        key: Item_Enums_1.EItemKey.letter_belt_up_3,
        image: "Res/items/LoyalCaestusLetterOfUpgradeResult.Lv3.jpg",
        name: "LoyalCaestusLetterOfUpgradeResult.Lv3.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalCaestusLetterOfUpgradeResult.Lv3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalCaestusLetterOfUpgradeResult.Lv3.itemDesc",
    },
    letter_necklace_up_2: {
        key: Item_Enums_1.EItemKey.letter_necklace_up_2,
        image: "Res/items/LoyalNecklaceLetterOfUpgradeResult.Lv2.jpg",
        name: "LoyalNecklaceLetterOfUpgradeResult.Lv2.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalNecklaceLetterOfUpgradeResult.Lv2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalNecklaceLetterOfUpgradeResult.Lv2.itemDesc",
    },
    letter_necklace_up_3: {
        key: Item_Enums_1.EItemKey.letter_necklace_up_3,
        image: "Res/items/LoyalNecklaceLetterOfUpgradeResult.Lv3.jpg",
        name: "LoyalNecklaceLetterOfUpgradeResult.Lv3.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalNecklaceLetterOfUpgradeResult.Lv3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalNecklaceLetterOfUpgradeResult.Lv3.itemDesc",
    },
    letter_ring_up_2: {
        key: Item_Enums_1.EItemKey.letter_ring_up_2,
        image: "Res/items/LoyalRingLetterOfUpgradeResult.Lv2.jpg",
        name: "LoyalRingLetterOfUpgradeResult.Lv2.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalRingLetterOfUpgradeResult.Lv2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalRingLetterOfUpgradeResult.Lv2.itemDesc",
    },
    letter_ring_up_3: {
        key: Item_Enums_1.EItemKey.letter_ring_up_3,
        image: "Res/items/LoyalRingLetterOfUpgradeResult.Lv3.jpg",
        name: "LoyalRingLetterOfUpgradeResult.Lv3.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalRingLetterOfUpgradeResult.Lv3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalRingLetterOfUpgradeResult.Lv3.itemDesc",
    },
    letter_steed_up_2: {
        key: Item_Enums_1.EItemKey.letter_steed_up_2,
        image: "Res/items/LoyalMountLetterOfUpgradeResult.Lv2.jpg",
        name: "LoyalMountLetterOfUpgradeResult.Lv2.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalMountLetterOfUpgradeResult.Lv2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalMountLetterOfUpgradeResult.Lv2.itemDesc",
    },
    letter_steed_up_3: {
        key: Item_Enums_1.EItemKey.letter_steed_up_3,
        image: "Res/items/LoyalMountLetterOfUpgradeResult.Lv3.jpg",
        name: "LoyalMountLetterOfUpgradeResult.Lv3.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalMountLetterOfUpgradeResult.Lv3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalMountLetterOfUpgradeResult.Lv3.itemDesc",
    },
    letter_pendant_1: {
        key: Item_Enums_1.EItemKey.letter_pendant_1,
        image: "Res/items/LoyalAccessoriesTicket.Lv1.jpg",
        name: "LoyalAccessoriesTicket.Lv1.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalAccessoriesTicket.Lv1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalAccessoriesTicket.Lv1.itemDesc",
    },
    letter_pendant_2: {
        key: Item_Enums_1.EItemKey.letter_pendant_2,
        image: "Res/items/LoyalAccessoriesTicket.Lv2.jpg",
        name: "LoyalAccessoriesTicket.Lv2.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalAccessoriesTicket.Lv2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalAccessoriesTicket.Lv2.itemDesc",
    },
    letter_pendant_3: {
        key: Item_Enums_1.EItemKey.letter_pendant_3,
        image: "Res/items/LoyalAccessoriesTicket.Lv3.jpg",
        name: "LoyalAccessoriesTicket.Lv3.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalAccessoriesTicket.Lv3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalAccessoriesTicket.Lv3.itemDesc",
    },
    letter_belt_1: {
        key: Item_Enums_1.EItemKey.letter_belt_1,
        image: "Res/items/LoyalCaestusTicket.Lv1.jpg",
        name: "LoyalCaestusTicket.Lv1.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalCaestusTicket.Lv1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalCaestusTicket.Lv1.itemDesc",
    },
    letter_belt_2: {
        key: Item_Enums_1.EItemKey.letter_belt_2,
        image: "Res/items/LoyalCaestusTicket.Lv2.jpg",
        name: "LoyalCaestusTicket.Lv2.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalCaestusTicket.Lv2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalCaestusTicket.Lv2.itemDesc",
    },
    letter_belt_3: {
        key: Item_Enums_1.EItemKey.letter_belt_3,
        image: "Res/items/LoyalCaestusTicket.Lv3.jpg",
        name: "LoyalCaestusTicket.Lv3.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalCaestusTicket.Lv3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalCaestusTicket.Lv3.itemDesc",
    },
    letter_necklace_1: {
        key: Item_Enums_1.EItemKey.letter_necklace_1,
        image: "Res/items/LoyalNecklaceTicket.Lv1.jpg",
        name: "LoyalNecklaceTicket.Lv1.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalNecklaceTicket.Lv1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalNecklaceTicket.Lv1.itemDesc",
    },
    letter_necklace_2: {
        key: Item_Enums_1.EItemKey.letter_necklace_2,
        image: "Res/items/LoyalNecklaceTicket.Lv2.jpg",
        name: "LoyalNecklaceTicket.Lv2.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalNecklaceTicket.Lv2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalNecklaceTicket.Lv2.itemDesc",
    },
    letter_necklace_3: {
        key: Item_Enums_1.EItemKey.letter_necklace_3,
        image: "Res/items/LoyalNecklaceTicket.Lv3.jpg",
        name: "LoyalNecklaceTicket.Lv3.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalNecklaceTicket.Lv3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalNecklaceTicket.Lv3.itemDesc",
    },
    letter_ring_1: {
        key: Item_Enums_1.EItemKey.letter_ring_1,
        image: "Res/items/LoyalRingTicket.Lv1.jpg",
        name: "LoyalRingTicket.Lv1.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalRingTicket.Lv1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalRingTicket.Lv1.itemDesc",
    },
    letter_ring_2: {
        key: Item_Enums_1.EItemKey.letter_ring_2,
        image: "Res/items/LoyalRingTicket.Lv2.jpg",
        name: "LoyalRingTicket.Lv2.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalRingTicket.Lv2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalRingTicket.Lv2.itemDesc",
    },
    letter_ring_3: {
        key: Item_Enums_1.EItemKey.letter_ring_3,
        image: "Res/items/LoyalRingTicket.Lv3.jpg",
        name: "LoyalRingTicket.Lv3.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalRingTicket.Lv3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalRingTicket.Lv3.itemDesc",
    },
    letter_steed_1: {
        key: Item_Enums_1.EItemKey.letter_steed_1,
        image: "Res/items/LoyalMountTicket.Lv1.jpg",
        name: "LoyalMountTicket.Lv1.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalMountTicket.Lv1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalMountTicket.Lv1.itemDesc",
    },
    letter_steed_2: {
        key: Item_Enums_1.EItemKey.letter_steed_2,
        image: "Res/items/LoyalMountTicket.Lv2.jpg",
        name: "LoyalMountTicket.Lv2.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalMountTicket.Lv2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalMountTicket.Lv2.itemDesc",
    },
    letter_steed_3: {
        key: Item_Enums_1.EItemKey.letter_steed_3,
        image: "Res/items/LoyalMountTicket.Lv3.jpg",
        name: "LoyalMountTicket.Lv3.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "LoyalMountTicket.Lv3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_box,
        db_tab: Item_Enums_1.EItemTable.matrial_box,
        long_desc: "LoyalMountTicket.Lv3.itemDesc",
    },
    luxury_1: {
        image: "Res/items/item071.jpg",
        key: Item_Enums_1.EItemKey.luxury_1,
        name: "hero.loyalty.1.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 20,
        desc: "hero.loyalty.1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_luxury,
        db_tab: Item_Enums_1.EItemTable.matrial_luxury,
        long_desc: "hero.loyalty.1.itemDesc",
    },
    luxury_2: {
        image: "Res/items/item072.jpg",
        key: Item_Enums_1.EItemKey.luxury_2,
        name: "hero.loyalty.2.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 25,
        desc: "hero.loyalty.2.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_luxury,
        db_tab: Item_Enums_1.EItemTable.matrial_luxury,
        long_desc: "hero.loyalty.2.itemDesc",
    },
    luxury_3: {
        image: "Res/items/item073.jpg",
        key: Item_Enums_1.EItemKey.luxury_3,
        name: "hero.loyalty.3.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 30,
        desc: "hero.loyalty.3.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_luxury,
        db_tab: Item_Enums_1.EItemTable.matrial_luxury,
        long_desc: "hero.loyalty.3.itemDesc",
    },
    luxury_4: {
        image: "Res/items/item074.jpg",
        key: Item_Enums_1.EItemKey.luxury_4,
        name: "hero.loyalty.4.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 35,
        desc: "hero.loyalty.4.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_luxury,
        db_tab: Item_Enums_1.EItemTable.matrial_luxury,
        long_desc: "hero.loyalty.4.itemDesc",
    },
    luxury_5: {
        image: "Res/items/item075.jpg",
        key: Item_Enums_1.EItemKey.luxury_5,
        name: "hero.loyalty.5.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 40,
        desc: "hero.loyalty.5.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_luxury,
        db_tab: Item_Enums_1.EItemTable.matrial_luxury,
        long_desc: "hero.loyalty.5.itemDesc",
    },
    luxury_6: {
        image: "Res/items/item076.jpg",
        key: Item_Enums_1.EItemKey.luxury_6,
        name: "hero.loyalty.6.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 45,
        desc: "hero.loyalty.6.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_luxury,
        db_tab: Item_Enums_1.EItemTable.matrial_luxury,
        long_desc: "hero.loyalty.6.itemDesc",
    },
    luxury_7: {
        image: "Res/items/item077.jpg",
        key: Item_Enums_1.EItemKey.luxury_7,
        name: "hero.loyalty.7.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 50,
        desc: "hero.loyalty.7.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_luxury,
        db_tab: Item_Enums_1.EItemTable.matrial_luxury,
        long_desc: "hero.loyalty.7.itemDesc",
    },
    luxury_8: {
        image: "Res/items/item078.jpg",
        key: Item_Enums_1.EItemKey.luxury_8,
        name: "hero.loyalty.8.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 55,
        desc: "hero.loyalty.8.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_luxury,
        db_tab: Item_Enums_1.EItemTable.matrial_luxury,
        long_desc: "hero.loyalty.8.itemDesc",
    },
    luxury_9: {
        image: "Res/items/item079.jpg",
        key: Item_Enums_1.EItemKey.luxury_9,
        name: "hero.loyalty.9.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 60,
        desc: "hero.loyalty.9.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_luxury,
        db_tab: Item_Enums_1.EItemTable.matrial_luxury,
        long_desc: "hero.loyalty.9.itemDesc",
    },
    flag_england: {
        image: "Res/items/Britons.jpg",
        key: Item_Enums_1.EItemKey.flag_england,
        name: "Britons.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "Britons.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Britons.itemDesc",
    },
    flag_france: {
        image: "Res/items/Gauls.jpg",
        key: Item_Enums_1.EItemKey.flag_france,
        name: "Gauls.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "Gauls.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Gauls.itemDesc",
    },
    flag_germany: {
        image: "Res/items/Germans.jpg",
        key: Item_Enums_1.EItemKey.flag_germany,
        name: "Germans.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "Germans.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Germans.itemDesc",
    },
    flag_magul: {
        image: "Res/items/Huns.jpg",
        key: Item_Enums_1.EItemKey.flag_magul,
        name: "Huns.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "Huns.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Huns.itemDesc",
    },
    flag_macdoni: {
        image: "Res/items/Macedon.jpg",
        key: Item_Enums_1.EItemKey.flag_macdoni,
        name: "Macedon.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "Macedon.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Macedon.itemDesc",
    },
    flag_roma: {
        image: "Res/items/Rome.jpg",
        key: Item_Enums_1.EItemKey.flag_roma,
        name: "Rome.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "Rome.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Rome.itemDesc",
    },
    flag_spain: {
        image: "Res/items/Spain.jpg",
        key: Item_Enums_1.EItemKey.flag_spain,
        name: "Spain.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "Spain.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Spain.itemDesc",
    },
    flag_cartaga: {
        image: "Res/items/Carthage.jpg",
        key: Item_Enums_1.EItemKey.flag_cartaga,
        name: "Carthage.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "Carthage.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Carthage.itemDesc",
    },
    flag_egypt: {
        image: "Res/items/Egypt.jpg",
        key: Item_Enums_1.EItemKey.flag_egypt,
        name: "Egypt.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "Egypt.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Egypt.itemDesc",
    },
    flag_greek: {
        image: "Res/items/Parthia.jpg",
        key: Item_Enums_1.EItemKey.flag_greek,
        name: "Parthia.name",
        req: [],
        use: Item_Enums_1.EItemUse.once,
        gold: 10,
        desc: "Parthia.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Parthia.itemDesc",
    },
    law_1: {
        image: "Res/items/CodeofDraco.jpg",
        key: Item_Enums_1.EItemKey.law_1,
        name: "CodeofDraco.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "CodeofDraco.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "CodeofDraco.itemDesc",
    },
    law_2: {
        image: "Res/items/LawoftheTwelveTables.jpg",
        key: Item_Enums_1.EItemKey.law_2,
        name: "LawoftheTwelveTables.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "LawoftheTwelveTables.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "LawoftheTwelveTables.itemDesc",
    },
    law_3: {
        image: "Res/items/RomanLaw.jpg",
        key: Item_Enums_1.EItemKey.law_3,
        name: "RomanLaw.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "RomanLaw.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "RomanLaw.itemDesc",
    },
    truce_pack: {
        image: "Res/items/Trans.pacification.jpg",
        key: Item_Enums_1.EItemKey.truce_pack,
        name: "Trans.pacification.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 15,
        desc: "Trans.pacification.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Trans.pacification.itemDesc",
    },
    prestige_slogan: {
        image: "Res/items/prestige1.jpg",
        key: Item_Enums_1.EItemKey.prestige_slogan,
        name: "prestige1.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 125,
        startingAmount: 1,
        desc: "prestige1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "prestige1.itemDesc",
    },
    lion_slo: {
        image: "Res/items/LionEmblem.jpg",
        key: Item_Enums_1.EItemKey.lion_slo,
        name: "LionEmblem.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "LionEmblem.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "LionEmblem.itemDesc",
    },
    lion_hrt: {
        image: "Res/items/LionHeart.jpg",
        key: Item_Enums_1.EItemKey.lion_hrt,
        name: "LionHeart.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "LionHeart.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "LionHeart.itemDesc",
    },
    brave_slo: {
        image: "Res/items/FearlessnessEmblem.jpg",
        key: Item_Enums_1.EItemKey.brave_slo,
        name: "FearlessnessEmblem.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "FearlessnessEmblem.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "FearlessnessEmblem.itemDesc",
    },
    horn_help: {
        image: "Res/items/HornofMars.jpg",
        key: Item_Enums_1.EItemKey.horn_help,
        name: "HornofMars.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 0,
        desc: "HornofMars.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "HornofMars.itemDesc",
    },
    mili_slo: {
        image: "Res/items/Trans.excellent.jpg",
        key: Item_Enums_1.EItemKey.mili_slo,
        name: "Trans.excellent.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "Trans.excellent.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Trans.excellent.itemDesc",
    },
    god_point_5: {
        image: "Res/items/item068.jpg",
        key: Item_Enums_1.EItemKey.god_point_5,
        name: "نقاط التسليح (5)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 1,
        desc: "يمكنك استخدام هذا الصندوق للحصول على 5 نقاط تسليح.",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "عند فتح هذا الصندوق، ستحصل على 5 نقاط تسليح لاستخدامها في تعزيز قدراتك القتالية.",
    },
    god_point_30: {
        image: "Res/items/item103.jpg",
        key: Item_Enums_1.EItemKey.god_point_30,
        name: "نقاط التسليح (30)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 5,
        desc: "يمكنك استخدام هذا الصندوق للحصول على 30 نقاط تسليح.",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "عند فتح هذا الصندوق، ستحصل على 30 نقاط تسليح لاستخدامها في تعزيز قدراتك القتالية.",
    },
    god_point_75: {
        image: "Res/items/item063.jpg",
        key: Item_Enums_1.EItemKey.god_point_75,
        name: "نقاط التسليح (75)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 10,
        desc: "يمكنك استخدام هذا الصندوق للحصول على 75 نقاط تسليح.",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "عند فتح هذا الصندوق، ستحصل على 75 نقاط تسليح لاستخدامها في تعزيز قدراتك القتالية.",
    },
    god_point_175: {
        image: "Res/items/item064.jpg",
        key: Item_Enums_1.EItemKey.god_point_175,
        name: "نقاط التسليح (175)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 20,
        desc: "يمكنك استخدام هذا الصندوق للحصول على 175 نقاط تسليح.",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "عند فتح هذا الصندوق، ستحصل على 175 نقاط تسليح لاستخدامها في تعزيز قدراتك القتالية.",
    },
    god_point_750: {
        image: "Res/items/item053.jpg",
        key: Item_Enums_1.EItemKey.god_point_750,
        name: "نقاط التسليح (750)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 80,
        desc: "يمكنك استخدام هذا الصندوق للحصول على 750 نقاط تسليح.",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "عند فتح هذا الصندوق، ستحصل على 750 نقاط تسليح لاستخدامها في تعزيز قدراتك القتالية.",
    },
    god_point_1k: {
        image: "Res/items/item054.jpg",
        key: Item_Enums_1.EItemKey.god_point_1k,
        name: "نقاط التسليح (1000)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 100,
        desc: "يمكنك استخدام هذا الصندوق للحصول على 1000 نقاط تسليح.",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "عند فتح هذا الصندوق، ستحصل على 1000 نقاط تسليح لاستخدامها في تعزيز قدراتك القتالية.",
    },
    god_point_2k: {
        image: "Res/items/item055.jpg",
        key: Item_Enums_1.EItemKey.god_point_2k,
        name: "نقاط التسليح (2000)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 180,
        desc: "يمكنك استخدام هذا الصندوق للحصول على 2000 نقاط تسليح.",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "عند فتح هذا الصندوق، ستحصل على 2000 نقاط تسليح لاستخدامها في تعزيز قدراتك القتالية.",
    },
    god_point_5k: {
        image: "Res/items/item056.jpg",
        key: Item_Enums_1.EItemKey.god_point_5k,
        name: "نقاط التسليح (5000)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 425,
        desc: "يمكنك استخدام هذا الصندوق للحصول على 5000 نقاط تسليح.",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "عند فتح هذا الصندوق، ستحصل على 5000 نقاط تسليح لاستخدامها في تعزيز قدراتك القتالية.",
    },
    god_point_10k: {
        image: "Res/items/item057.jpg",
        key: Item_Enums_1.EItemKey.god_point_10k,
        name: "نقاط التسليح (10000)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 800,
        desc: "يمكنك استخدام هذا الصندوق للحصول على 10000 نقاط تسليح.",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "عند فتح هذا الصندوق، ستحصل على 10000 نقاط تسليح لاستخدامها في تعزيز قدراتك القتالية.",
    },
    god_point_50k: {
        image: "Res/items/item058.jpg",
        key: Item_Enums_1.EItemKey.god_point_50k,
        name: "نقاط التسليح (50000)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 3800,
        desc: "يمكنك استخدام هذا الصندوق للحصول على 50000 نقاط تسليح.",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "عند فتح هذا الصندوق، ستحصل على 50000 نقاط تسليح لاستخدامها في تعزيز قدراتك القتالية.",
    },
    arena_attempt_1: {
        key: Item_Enums_1.EItemKey.arena_attempt_1,
        image: "Res/items/festival_blue.jpg",
        name: "صندوق جولة (1)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 5,
        desc: "افتح الصندوق للحصول على على1 جولات ميدان تحدى لمحاولة السيطرة علية!",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "افتح الصندوق للحصول على على1 جولات ميدان تحدى لمحاولة السيطرة علية!",
    },
    arena_attempt_5: {
        key: Item_Enums_1.EItemKey.arena_attempt_5,
        image: "Res/items/festival_gray.jpg",
        name: "صندوق جولة (5)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 20,
        desc: "افتح الصندوق للحصول على على5 جولات ميدان تحدى لمحاولة السيطرة علية!",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "افتح الصندوق للحصول على على5 جولات ميدان تحدى لمحاولة السيطرة علية!",
    },
    arena_attempt_10: {
        key: Item_Enums_1.EItemKey.arena_attempt_10,
        image: "Res/items/festival_green.jpg",
        name: "صندوق جولة (10)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 35,
        desc: "افتح الصندوق للحصول على على10 جولات ميدان تحدى لمحاولة السيطرة علية!",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "افتح الصندوق للحصول على على10 جولات ميدان تحدى لمحاولة السيطرة علية!",
    },
    arena_exp_1: {
        key: Item_Enums_1.EItemKey.arena_exp_1,
        image: "Res/items/festival_orange.jpg",
        name: "صندوق خبرة ميدان (1)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 2,
        desc: "افتح الصندوق للحصول على خبرة ميدان(1) لتحدى القياصرة وتطوير الميدان لاستيعاب ابطال اكثر",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "افتح الصندوق للحصول على خبرة ميدان(1) لتحدى القياصرة وتطوير الميدان لاستيعاب ابطال اكثر",
    },
    arena_exp_5: {
        key: Item_Enums_1.EItemKey.arena_exp_5,
        image: "Res/items/festival_purple.jpg",
        name: "صندوق خبرة ميدان (5)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 5,
        desc: "افتح الصندوق للحصول على خبرة ميدان(5) لتحدى القياصرة وتطوير الميدان لاستيعاب ابطال اكثر",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "افتح الصندوق للحصول على خبرة ميدان(5) لتحدى القياصرة وتطوير الميدان لاستيعاب ابطال اكثر",
    },
    arena_exp_10: {
        key: Item_Enums_1.EItemKey.arena_exp_10,
        image: "Res/items/festival_special.jpg",
        name: "صندوق خبرة ميدان (10)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 9,
        desc: "افتح الصندوق للحصول على خبرة ميدان(10) لتحدى القياصرة وتطوير الميدان لاستيعاب ابطال اكثر",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "افتح الصندوق للحصول على خبرة ميدان(10) لتحدى القياصرة وتطوير الميدان لاستيعاب ابطال اكثر",
    },
    arena_exp_25: {
        key: Item_Enums_1.EItemKey.arena_exp_25,
        image: "Res/items/festival_yellow.jpg",
        name: "صندوق خبرة ميدان (25)",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 20,
        desc: "افتح الصندوق للحصول على خبرة ميدان(25) لتحدى القياصرة وتطوير الميدان لاستيعاب ابطال اكثر",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "افتح الصندوق للحصول على خبرة ميدان(25) لتحدى القياصرة وتطوير الميدان لاستيعاب ابطال اكثر",
    },
    gold_1: {
        key: Item_Enums_1.EItemKey.gold_1,
        image: "Res/items/item080.jpg",
        name: "حزمة ذهب 1",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "استعملها لتحصل على 1 ذهب",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "استعملها لتحصل على 1 ذهب",
    },
    gold_5: {
        key: Item_Enums_1.EItemKey.gold_5,
        image: "Res/items/item080.jpg",
        name: "حزمة ذهب 5",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "استعملها لتحصل على 5 ذهب",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "استعملها لتحصل على 5 ذهب",
    },
    gold_10: {
        key: Item_Enums_1.EItemKey.gold_10,
        image: "Res/items/item080.jpg",
        name: "_number.player.box.gambling.medal.10.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "_number.player.box.gambling.medal.10.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "_number.player.box.gambling.medal.10.itemDesc",
    },
    gold_25: {
        key: Item_Enums_1.EItemKey.gold_25,
        image: "Res/items/item117.jpg",
        name: "_number.player.box.gambling.medal.30.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        startingAmount: 1,
        desc: "_number.player.box.gambling.medal.30.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "_number.player.box.gambling.medal.30.itemDesc",
    },
    gold_75: {
        key: Item_Enums_1.EItemKey.gold_75,
        image: "Res/items/item117.jpg",
        name: "_number.player.box.gambling.medal.50.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "_number.player.box.gambling.medal.50.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "_number.player.box.gambling.medal.50.itemDesc",
    },
    gold_100: {
        key: Item_Enums_1.EItemKey.gold_100,
        image: "Res/items/item117.jpg",
        name: "_number.player.box.gambling.medal.100.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "_number.player.box.gambling.medal.100.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "_number.player.box.gambling.medal.100.itemDesc",
    },
    gold_500: {
        key: Item_Enums_1.EItemKey.gold_500,
        image: "Res/items/item065.jpg",
        name: "_number.player.box.gambling.medal.300.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "_number.player.box.gambling.medal.300.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "_number.player.box.gambling.medal.300.itemDesc",
    },
    gold_1000: {
        key: Item_Enums_1.EItemKey.gold_1000,
        image: "Res/items/item065.jpg",
        name: "_number.player.box.gambling.medal.1000.name",
        req: [],
        use: Item_Enums_1.EItemUse.many,
        gold: 0,
        desc: "_number.player.box.gambling.medal.1000.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "_number.player.box.gambling.medal.1000.itemDesc",
    },
    offer_pronze_bar_i: {
        key: Item_Enums_1.EItemKey.offer_pronze_bar_i,
        image: "Res/items/BronzeBarPackI.jpg",
        name: "BronzeBarPackI.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "BronzeBarPackI.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "BronzeBarPackI.itemDesc",
    },
    offer_pronze_bar_ii: {
        key: Item_Enums_1.EItemKey.offer_pronze_bar_ii,
        image: "Res/items/BronzeBarPackII.jpg",
        name: "BronzeBarPackII.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "BronzeBarPackII.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "BronzeBarPackII.itemDesc",
    },
    offer_draco: {
        key: Item_Enums_1.EItemKey.offer_draco,
        image: "Res/items/CodeofDraco.Pocket.jpg",
        name: "CodeofDraco.Pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 500,
        desc: "CodeofDraco.Pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "CodeofDraco.Pocket.itemDesc",
    },
    offer_flags: {
        key: Item_Enums_1.EItemKey.offer_flags,
        image: "Res/items/EmblemPack.jpg",
        name: "EmblemPack.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "EmblemPack.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "EmblemPack.itemDesc",
    },
    offer_motiv: {
        key: Item_Enums_1.EItemKey.offer_motiv,
        image: "Res/items/Developcity.pocket.jpg",
        name: "Developcity.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 200,
        desc: "Developcity.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Developcity.pocket.itemDesc",
    },
    offer_gold_medal: {
        key: Item_Enums_1.EItemKey.offer_gold_medal,
        image: "Res/items/GoldMedal.Pocket.jpg",
        name: "GoldMedal.Pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "GoldMedal.Pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "GoldMedal.Pocket.itemDesc",
    },
    offer_lux_1: {
        key: Item_Enums_1.EItemKey.offer_lux_1,
        image: "Res/items/HonorI.jpg",
        name: "HonorI.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "HonorI.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "HonorI.itemDesc",
    },
    offer_lux_2: {
        key: Item_Enums_1.EItemKey.offer_lux_2,
        image: "Res/items/HonorII.jpg",
        name: "HonorII.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "HonorII.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "HonorII.itemDesc",
    },
    offer_lux_3: {
        key: Item_Enums_1.EItemKey.offer_lux_3,
        image: "Res/items/HonorIII.jpg",
        name: "HonorIII.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "HonorIII.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "HonorIII.itemDesc",
    },
    offer_lux_4: {
        key: Item_Enums_1.EItemKey.offer_lux_4,
        image: "Res/items/homeI.jpg",
        name: "homeI.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "homeI.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "homeI.itemDesc",
    },
    offer_lux_5: {
        key: Item_Enums_1.EItemKey.offer_lux_5,
        image: "Res/items/homeII.jpg",
        name: "homeII.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "homeII.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "homeII.itemDesc",
    },
    offer_lux_6: {
        key: Item_Enums_1.EItemKey.offer_lux_6,
        image: "Res/items/homeIII.jpg",
        name: "homeIII.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "homeIII.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "homeIII.itemDesc",
    },
    offer_lux_7: {
        key: Item_Enums_1.EItemKey.offer_lux_7,
        image: "Res/items/homeIV.jpg",
        name: "homeIV.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "homeIV.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "homeIV.itemDesc",
    },
    offer_king_medal: {
        key: Item_Enums_1.EItemKey.offer_king_medal,
        image: "Res/items/Mars.pocket.jpg",
        name: "Mars.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "Mars.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Mars.pocket.itemDesc",
    },
    offer_MinervaHolyWater_1: {
        key: Item_Enums_1.EItemKey.offer_MinervaHolyWater_1,
        image: "Res/items/MinervaHolyWater(L)PackI.jpg",
        name: "MinervaHolyWater(L)PackI.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "MinervaHolyWater(L)PackI.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "MinervaHolyWater(L)PackI.itemDesc",
    },
    offer_MinervaHolyWater_2: {
        key: Item_Enums_1.EItemKey.offer_MinervaHolyWater_2,
        image: "Res/items/MinervaHolyWater(L)PackII.jpg",
        name: "MinervaHolyWater(L)PackII.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "MinervaHolyWater(L)PackII.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "MinervaHolyWater(L)PackII.itemDesc",
    },
    offer_peace: {
        key: Item_Enums_1.EItemKey.offer_peace,
        image: "Res/items/Newdeparture.jpg",
        name: "Newdeparture.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "Newdeparture.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Newdeparture.itemDesc",
    },
    offer_silver_bar_1: {
        key: Item_Enums_1.EItemKey.offer_silver_bar_1,
        image: "Res/items/SilverBarPackI.jpg",
        name: "SilverBarPackI.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 25,
        desc: "SilverBarPackI.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "SilverBarPackI.itemDesc",
    },
    offer_silver_bar_2: {
        key: Item_Enums_1.EItemKey.offer_silver_bar_2,
        image: "Res/items/SilverBarPackII.jpg",
        name: "SilverBarPackII.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "SilverBarPackII.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "SilverBarPackII.itemDesc",
    },
    offer_silver_bar_3: {
        key: Item_Enums_1.EItemKey.offer_silver_bar_3,
        image: "Res/items/SilverBarPackIII.jpg",
        name: "SilverBarPackIII.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "SilverBarPackIII.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "SilverBarPackIII.itemDesc",
    },
    offer_silver_bar_4: {
        key: Item_Enums_1.EItemKey.offer_silver_bar_4,
        image: "Res/items/SilverBarPackIV.jpg",
        name: "SilverBarPackIV.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "SilverBarPackIV.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "SilverBarPackIV.itemDesc",
    },
    offer_silver_bar_5: {
        key: Item_Enums_1.EItemKey.offer_silver_bar_5,
        image: "Res/items/SilverBarPackV.jpg",
        name: "SilverBarPackV.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "SilverBarPackV.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "SilverBarPackV.itemDesc",
    },
    offer_silver_bar_6: {
        key: Item_Enums_1.EItemKey.offer_silver_bar_6,
        image: "Res/items/SilverBarPackVI.jpg",
        name: "SilverBarPackVI.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 150,
        desc: "SilverBarPackVI.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "SilverBarPackVI.itemDesc",
    },
    offer_truce: {
        key: Item_Enums_1.EItemKey.offer_truce,
        image: "Res/items/Trans.pacification.Pocket.jpg",
        name: "Trans.pacification.Pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 90,
        desc: "Trans.pacification.Pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "Trans.pacification.Pocket.itemDesc",
    },
    offer_hammer: {
        key: Item_Enums_1.EItemKey.offer_hammer,
        image: "Res/items/crazyIII.jpg",
        name: "crazyIII.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 50,
        desc: "crazyIII.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "crazyIII.itemDesc",
    },
    offer_hero_exp: {
        key: Item_Enums_1.EItemKey.offer_hero_exp,
        image: "Res/items/herogrowth.Pocket.jpg",
        name: "herogrowth.Pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 600,
        desc: "herogrowth.Pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "herogrowth.Pocket.itemDesc",
    },
    offer_production_pack: {
        key: Item_Enums_1.EItemKey.offer_production_pack,
        image: "Res/items/incResall.pocket.jpg",
        name: "incResall.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "incResall.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "incResall.pocket.itemDesc",
    },
    offer_production_pack_2: {
        key: Item_Enums_1.EItemKey.offer_production_pack_2,
        image: "Res/items/incReshead.pocket.jpg",
        name: "incReshead.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "incReshead.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "incReshead.pocket.itemDesc",
    },
    offer_luck_1: {
        key: Item_Enums_1.EItemKey.offer_luck_1,
        image: "Res/items/sacrifice.Pocket.jpg",
        name: "sacrifice.Pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "sacrifice.Pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "sacrifice.Pocket.itemDesc",
    },
    offer_luck_2: {
        key: Item_Enums_1.EItemKey.offer_luck_2,
        image: "Res/items/sacrifice.Pocket1.jpg",
        name: "sacrifice.Pocket1.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 240,
        desc: "sacrifice.Pocket1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "sacrifice.Pocket1.itemDesc",
    },
    offer_archit_1: {
        key: Item_Enums_1.EItemKey.offer_archit_1,
        image: "Res/items/speedup15mBuilding.pocket.jpg",
        name: "speedup15mBuilding.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 50,
        desc: "speedup15mBuilding.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "speedup15mBuilding.pocket.itemDesc",
    },
    offer_archit_2: {
        key: Item_Enums_1.EItemKey.offer_archit_2,
        image: "Res/items/speedup1hBuilding.pocket.jpg",
        name: "speedup1hBuilding.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 100,
        desc: "speedup1hBuilding.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "speedup1hBuilding.pocket.itemDesc",
    },
    offer_archit_3: {
        key: Item_Enums_1.EItemKey.offer_archit_3,
        image: "Res/items/speedup3hBuilding.pocket.jpg",
        name: "speedup3hBuilding.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 200,
        desc: "speedup3hBuilding.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "speedup3hBuilding.pocket.itemDesc",
    },
    offer_archit_4: {
        key: Item_Enums_1.EItemKey.offer_archit_4,
        image: "Res/items/speedup30pBuilding.pocket.jpg",
        name: "speedup30pBuilding.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 1000,
        desc: "speedup30pBuilding.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "speedup30pBuilding.pocket.itemDesc",
    },
    offer_jop_1: {
        key: Item_Enums_1.EItemKey.offer_jop_1,
        image: "Res/items/speedup15mTraining.pocket.jpg",
        name: "speedup15mTraining.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "speedup15mTraining.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "speedup15mTraining.pocket.itemDesc",
    },
    offer_jop_2: {
        key: Item_Enums_1.EItemKey.offer_jop_2,
        image: "Res/items/speedup1hTraining.pocket.jpg",
        name: "speedup1hTraining.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "speedup1hTraining.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "speedup1hTraining.pocket.itemDesc",
    },
    offer_jop_3: {
        key: Item_Enums_1.EItemKey.offer_jop_3,
        image: "Res/items/speedup3hTraining.pocket.jpg",
        name: "speedup3hTraining.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "speedup3hTraining.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "speedup3hTraining.pocket.itemDesc",
    },
    offer_jop_4: {
        key: Item_Enums_1.EItemKey.offer_jop_4,
        image: "Res/items/speedup30pTraining.pocket.jpg",
        name: "speedup30pTraining.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "speedup30pTraining.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "speedup30pTraining.pocket.itemDesc",
    },
    offer_building_speed: {
        key: Item_Enums_1.EItemKey.offer_building_speed,
        image: "Res/items/speedup3hBuilding.pocket1.jpg",
        name: "speedup3hBuilding.pocket1.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "speedup3hBuilding.pocket1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "speedup3hBuilding.pocket1.itemDesc",
    },
    offer_marcus_scheme: {
        key: Item_Enums_1.EItemKey.offer_marcus_scheme,
        image: "Res/items/upgradeBuilding.pocket.jpg",
        name: "upgradeBuilding.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "upgradeBuilding.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "upgradeBuilding.pocket.itemDesc",
    },
    offer_expan_plan: {
        key: Item_Enums_1.EItemKey.offer_expan_plan,
        image: "Res/items/upgradeCity.pocket.jpg",
        name: "upgradeCity.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 150,
        desc: "upgradeCity.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "upgradeCity.pocket.itemDesc",
    },
    offer_expan_plan_2: {
        key: Item_Enums_1.EItemKey.offer_expan_plan_2,
        image: "Res/items/upgradeCity.pocket1.jpg",
        name: "upgradeCity.pocket1.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "upgradeCity.pocket1.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "upgradeCity.pocket1.itemDesc",
    },
    offer_war: {
        key: Item_Enums_1.EItemKey.offer_war,
        image: "Res/items/war.pocket.jpg",
        name: "war.pocket.name",
        req: [],
        use: Item_Enums_1.EItemUse.box,
        gold: 0,
        desc: "war.pocket.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_flags,
        db_tab: Item_Enums_1.EItemTable.matrial_flags,
        long_desc: "war.pocket.itemDesc",
    },
    demigod_papirus: {
        image: "Res/items/DemigodPapirus.jpg",
        key: Item_Enums_1.EItemKey.demigod_papirus,
        name: "DemigodPapirus.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "DemigodPapirus.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "DemigodPapirus.itemDesc",
    },
    demigod_power: {
        image: "Res/items/DemigodPower.jpg",
        key: Item_Enums_1.EItemKey.demigod_power,
        name: "DemigodPower.name",
        req: [],
        use: Item_Enums_1.EItemUse.none,
        gold: 100,
        desc: "DemigodPower.funcDesc",
        table: Item_Enums_1.EItemTable.matrial_main,
        db_tab: Item_Enums_1.EItemTable.matrial_main,
        long_desc: "DemigodPower.itemDesc",
    },
};


/***/ },

/***/ "../Common/XDate.ts"
/*!**************************!*\
  !*** ../Common/XDate.ts ***!
  \**************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.XDate = void 0;
class XDate {
    static UtcNow() {
        return Math.floor(Date.now() / 1000);
    }
    static dateTimeFormatShort(time) {
        const monthNames = [
            "Jan",
            "Feb",
            "Mar",
            "Apr",
            "May",
            "Jun",
            "Jul",
            "Aug",
            "Sep",
            "Oct",
            "Nov",
            "Dec",
        ];
        // return `${time.getUTCDate()} ${monthNames[time.getUTCMonth()]} ${time.getUTCFullYear()} ${time.getUTCHours()}:${time.getUTCMinutes()}`;
        const lDate = new Date(time * 1000);
        return `${lDate.getUTCDate()} ${monthNames[lDate.getUTCMonth()]} ${lDate.getUTCHours()}:${lDate.getUTCMinutes()}`;
    }
    static changeTimeFormat(time_diff, hideSec) {
        if (!time_diff || isNaN(time_diff))
            return "---";
        time_diff = time_diff;
        const days = Math.floor(time_diff / (60 * 60 * 24));
        const hours = Math.floor((time_diff % (60 * 60 * 24)) / (60 * 60));
        const minutes = Math.floor((time_diff % (60 * 60)) / 60);
        const seconds = Math.floor((time_diff % 60) / 1);
        let lSec = "";
        if (!hideSec) {
            lSec = seconds + "&nbsp;ث&nbsp;";
        }
        return (lSec +
            (minutes > 0 ? minutes + "د&nbsp;" : "") +
            (hours > 0 ? hours + "س&nbsp;" : "") +
            (days > 0 ? days + "ى&nbsp;" : " "));
    }
    static lastSeen(last_seen) {
        var time_diff = Math.floor(this.UtcNow() - last_seen);
        if (time_diff < 60) {
            return `منذ ${Math.floor(time_diff)} ثانية`;
        }
        else if (time_diff < 60 * 11) {
            return `منذ ${Math.floor(time_diff / 60)} دقائق`;
        }
        else if (time_diff < 60 * 60) {
            return `منذ ${Math.floor(time_diff / 60)} دقيقة`;
        }
        else if (time_diff < 60 * 60 * 11) {
            return `منذ ${Math.floor(time_diff / (60 * 60))} ساعات`;
        }
        else if (time_diff < 60 * 60 * 24) {
            return `منذ ${Math.floor(time_diff / (60 * 60))} ساعة`;
        }
        else if (time_diff < 60 * 60 * 24 * 7) {
            return "يوم" + Math.floor(time_diff / (60 * 60 * 24)) + "منذ ";
        }
        else {
            return "منذ فترة طويلة";
        }
    }
}
exports.XDate = XDate;


/***/ },

/***/ "../Common/XLog.ts"
/*!*************************!*\
  !*** ../Common/XLog.ts ***!
  \*************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.XAssert = exports.XLog = void 0;
class XLog {
    static Info(...args) {
        console.log("[INFO]", ...args);
    }
    static Warn(...args) {
        console.warn("[WARN]", ...args);
    }
    static Error(...args) {
        console.error("[ERROR]", ...args);
    }
    static log(...args) {
        console.log("[LOG]", ...args);
    }
    static Fatal(...args) {
        console.error("[FATAL]", ...args);
        throw new Error("Fatal Error");
    }
}
exports.XLog = XLog;
class XAssert {
    static Panic(condition, ...args) {
        if (!condition) {
            XLog.Error("Assertion Failed:", ...args);
            throw new Error("Assertion Failed");
        }
    }
}
exports.XAssert = XAssert;


/***/ },

/***/ "../Static/css/alert-box.css"
/*!***********************************!*\
  !*** ../Static/css/alert-box.css ***!
  \***********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "../Static/css/base.css"
/*!******************************!*\
  !*** ../Static/css/base.css ***!
  \******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "../Static/css/city.css"
/*!******************************!*\
  !*** ../Static/css/city.css ***!
  \******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "../Static/css/fonts-min.css"
/*!***********************************!*\
  !*** ../Static/css/fonts-min.css ***!
  \***********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "../Static/css/lastOfLast.css"
/*!************************************!*\
  !*** ../Static/css/lastOfLast.css ***!
  \************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "../Static/css/lastStyle.css"
/*!***********************************!*\
  !*** ../Static/css/lastStyle.css ***!
  \***********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "../Static/css/reset-min.css"
/*!***********************************!*\
  !*** ../Static/css/reset-min.css ***!
  \***********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "../Static/css/ui.css"
/*!****************************!*\
  !*** ../Static/css/ui.css ***!
  \****************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "../Static/css/world.css"
/*!*******************************!*\
  !*** ../Static/css/world.css ***!
  \*******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./Src/BR/BRHero.Service.ts"
/*!**********************************!*\
  !*** ./Src/BR/BRHero.Service.ts ***!
  \**********************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.BRHeroService = void 0;
const BattleReplay_Service_1 = __webpack_require__(/*! ../BattleReplay.Service */ "./Src/BattleReplay.Service.ts");
class BRHeroService {
    static GetHeroData(idHero) {
        return BattleReplay_Service_1.BattleReplayService.BattelReplayData.hero_list.find((h) => h.Hero.id_hero === idHero);
    }
}
exports.BRHeroService = BRHeroService;


/***/ },

/***/ "./Src/BR/BRHero.ts"
/*!**************************!*\
  !*** ./Src/BR/BRHero.ts ***!
  \**************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.BRHero = exports._InFieldHero = void 0;
const Battle_Enum_1 = __webpack_require__(/*! ../../../Common/Battle/Battle.Enum */ "../Common/Battle/Battle.Enum.ts");
const Army_Enum_1 = __webpack_require__(/*! ../../../Common/Enum/Army.Enum */ "../Common/Enum/Army.Enum.ts");
const Hero_Config_1 = __webpack_require__(/*! ../../../Common/Hero/Hero.Config */ "../Common/Hero/Hero.Config.ts");
const Hero_Enum_1 = __webpack_require__(/*! ../../../Common/Hero/Hero.Enum */ "../Common/Hero/Hero.Enum.ts");
const XLog_1 = __webpack_require__(/*! ../../../Common/XLog */ "../Common/XLog.ts");
const Base_1 = __webpack_require__(/*! ./Base */ "./Src/BR/Base.ts");
const BRHero_Service_1 = __webpack_require__(/*! ./BRHero.Service */ "./Src/BR/BRHero.Service.ts");
const BRHeroCell_1 = __webpack_require__(/*! ./BRHeroCell */ "./Src/BR/BRHeroCell.ts");
const BRPlayer_Service_1 = __webpack_require__(/*! ./BRPlayer.Service */ "./Src/BR/BRPlayer.Service.ts");
class _InFieldHero {
}
exports._InFieldHero = _InFieldHero;
class BRHero extends ccui.Widget {
    constructor() {
        super(...arguments);
        this._HeroCells = new Map();
    }
    static Create(Hero, round) {
        const lHero = new BRHero();
        lHero._Round = round;
        lHero._HeroIndex = Hero?.HeroIndex ?? Hero_Enum_1.EHeroPlaceInFightRound.ATT_1;
        lHero._Hero = Hero;
        for (const [lHeroCellIndex, lHeroCell] of lHero._Round._HeroList) {
            if (Hero?.idHero === lHeroCell._Hero?.idHero) {
                return undefined;
            }
        }
        return lHero;
    }
    onEnter() {
        super.onEnter();
        // if (BRRound._InFieldHeros[this._HeroIndex]) {
        //   const lHeroData = BRRound._InFieldHeros[this._HeroIndex];
        //   const lBlockList = BRRound._Blocks[this._HeroIndex];
        //   if (lHeroData) {
        //     for (const iii in lBlockList) {
        //       const lBlockIndex = Number(iii) as EArmyPlaceOnHero;
        //       const lBlock = lBlockList[lBlockIndex] as BRHeroCell;
        //       if (lHeroData.ArmyBlocks?.[lBlockIndex] && lBlock._ArmyAmount) {
        //         lBlock._ArmyAmount.setString(
        //           lHeroData.ArmyBlocks[lBlockIndex].unit.toString(),
        //         );
        //       }
        //     }
        //     return;
        //   }
        // }
        this.AddHero();
    }
    async PlaceAt(x, y) {
        const lThis = this;
        return new Promise((resolve) => {
            const lSeq = cc.sequence(cc.moveTo(Base_1.CBRConfigDelay.PosHero, x, y), cc.callFunc(() => {
                XLog_1.XLog.log("BRHero", "PlaceAt", lThis._HeroIndex, x, y);
                resolve();
            }));
            lThis.runAction(lSeq);
        });
    }
    AddHero() {
        const x = this._HeroIndex % 3;
        const y = Math.floor(this._HeroIndex / 3);
        const tx = (x - y) * 250;
        const ty = (x + y) * 125;
        let Center = {
            x: tx,
            y: ty,
        };
        if (this._HeroIndex < 3) {
            Center = {
                x: tx,
                y: ty,
            };
        }
        // BRRound._InFieldHeros[this._HeroIndex] = this._Hero;
        // BRRound._Blocks[this._HeroIndex] = {} as any;
        this._PlayerProfile = new ccui.Widget();
        this.AddBlock(Army_Enum_1.EArmyPlaceOnHero.b_1);
        this.AddBlock(Army_Enum_1.EArmyPlaceOnHero.b_2);
        this.AddBlock(Army_Enum_1.EArmyPlaceOnHero.b_3);
        this.AddBlock(Army_Enum_1.EArmyPlaceOnHero.f_1);
        this.AddBlock(Army_Enum_1.EArmyPlaceOnHero.f_2);
        this.AddBlock(Army_Enum_1.EArmyPlaceOnHero.f_3);
        this.AddHeroProf(Center);
        this.AddPlayerProf(Center);
    }
    AddBlock(BlockIndex) {
        //         var x = BlockIndex % 3;
        const x = BlockIndex % 3;
        //         var y = Math.floor(BlockIndex / 3);
        const y = Math.floor(BlockIndex / 3);
        //         var xBadding = 64;
        const xPadding = 60;
        //         var yBadding = 32;
        const yPadding = 30;
        //         var tx = (x - y) * xBadding;
        let tx = (x - y) * xPadding;
        //         var ty = (x + y) * yBadding;
        let ty = (x + y) * yPadding;
        //         if (this.HeroIndex < 3) {
        //             tx = (x + y) * xBadding;
        //             ty = (x - y) * yBadding;
        //         }
        if (this._HeroIndex < 3) {
            tx = (x + y) * xPadding;
            ty = (x - y) * yPadding;
        }
        //         if (Number(this.Hero.ArmyBlocks[BlockIndex].unit) <= 0)
        //             return;
        if (!this._Hero?.ArmyBlocks[BlockIndex].unit ||
            Number(this._Hero?.ArmyBlocks[BlockIndex].unit) <= 0) {
            return;
        }
        //         var ArmyBlock =
        //                 (new BRHeroCell(
        //                         this.Scene,
        //                         {x: tx + Center.x, y: ty + Center.y, BlockPaddingX: tx, BlockPaddingY: ty},
        //                         {HeroIndex: this.HeroIndex, ArmyType: this.Hero.ArmyBlocks[BlockIndex].armyType, BlockIndex: BlockIndex, ArmyCount: this.Hero.ArmyBlocks[BlockIndex].unit, side: this.Hero.side}
        //                 , this))
        const lArmyBlock = BRHeroCell_1.BRHeroCell.Create({
            armyType: this._Hero.ArmyBlocks[BlockIndex].armyType,
            unit: this._Hero.ArmyBlocks[BlockIndex].unit,
            dead: this._Hero.ArmyBlocks[BlockIndex].dead,
        }, {
            HeroIndex: this._HeroIndex,
            BlockIndex,
            side: this._Hero.side,
        }, {
            showDelay: /*this._showDelay*/ 0,
        });
        lArmyBlock.AddLargeBlock();
        lArmyBlock.setPosition(tx, -ty);
        // BRRound._Blocks[this._HeroIndex][BlockIndex] = lArmyBlock;
        this._HeroCells.set(BlockIndex, lArmyBlock);
        if ([
            Hero_Enum_1.EHeroPlaceInFightRound.ATT_1,
            Hero_Enum_1.EHeroPlaceInFightRound.ATT_2,
            Hero_Enum_1.EHeroPlaceInFightRound.ATT_3,
        ].includes(this._HeroIndex)) {
            lArmyBlock.setLocalZOrder(6 + BlockIndex + 1000);
        }
        else {
            if (BlockIndex >= Army_Enum_1.EArmyPlaceOnHero.b_1) {
                lArmyBlock.setLocalZOrder(BlockIndex - 3 + 1000);
            }
            else {
                lArmyBlock.setLocalZOrder(6 + BlockIndex + 1000);
            }
        }
        lArmyBlock.GetSoldierAnimation(Battle_Enum_1.ESoldierStateInBattle.Idle);
        this.addChild(lArmyBlock, lArmyBlock.getLocalZOrder());
    }
    SweapHero() {
        let lHeroEmpty = true;
        for (const [lBlockIndex, lArmyBlock] of this._HeroCells) {
            if (lArmyBlock._BlockData.unit > 0) {
                lArmyBlock.BlockDead();
                lHeroEmpty = false;
            }
            else {
                lArmyBlock.RemoveBlock();
            }
        }
        return lHeroEmpty;
    }
    AddHeroProf(Center) {
        let ty = 22;
        let tx = 170;
        const lProfile = new cc.Node();
        this.addChild(lProfile);
        if (this._HeroIndex > Hero_Enum_1.EHeroPlaceInFightRound.DEF_3) {
            tx = -48;
            ty = -138;
        }
        lProfile.setPosition(tx, ty);
        XLog_1.XLog.log(`Hero Index ${this._HeroIndex} Hero Name ${BRHero_Service_1.BRHeroService.GetHeroData(this._Hero?.idHero ?? 0)?.Hero.name}`);
        const lHeroName = new cc.LabelTTF();
        lHeroName.setPosition(0, 0);
        lHeroName.setString(BRHero_Service_1.BRHeroService.GetHeroData(this._Hero?.idHero ?? 0)?.Hero.name ??
            "Invalid Hero");
        lHeroName.setFontSize(14);
        lHeroName.setFontFillColor(cc.color(255, 255, 255));
        lHeroName.enableStroke(cc.color(0, 0, 0), 3);
        lHeroName.setHorizontalAlignment(cc.TEXT_ALIGNMENT_CENTER);
        // lHeroName.setDimensions(100, 0);
        lHeroName.setLocalZOrder(1000);
        const lHeroAvatar = new ccui.ImageView();
        lHeroAvatar.setPosition(0, 28);
        const lHeroData = BRHero_Service_1.BRHeroService.GetHeroData(this._Hero?.idHero ?? 0);
        if (lHeroData) {
            lHeroAvatar.loadTexture(Hero_Config_1.CHeroConfig[lHeroData.Hero.avatar].Icon, ccui.Widget.LOCAL_TEXTURE);
            lHeroAvatar.setLocalZOrder(10);
        }
        lHeroAvatar.loadTexture(Hero_Config_1.CHeroConfig[lHeroData?.Hero?.avatar ?? 0].Icon, ccui.Widget.LOCAL_TEXTURE);
        lHeroAvatar.setLocalZOrder(10);
        //         this.Scene.tweens.add({
        //             targets: [T],
        //             x: tx + Center.x,
        //             y: ty + Center.y,
        //             ease: 'Power1',
        //             duration: Elkaisar.Config.Delay.PosHero,
        //             delay: This.ShowDelay
        //         });
        // lHeroName.runAction(
        //   cc.sequence(
        //     cc.delayTime(this._showDelay),
        //     cc.moveTo(CBRConfigDelay.PosHero, tx + Center.x, ty + Center.y),
        //   ),
        // );
        //         if (PlayerAvatar)
        //         this.Scene.tweens.add({
        //             targets: [I],
        //             x: tx + Center.x + 50,
        //             y: ty + Center.y - 20,
        //             ease: 'Power1',
        //             duration: Elkaisar.Config.Delay.PosHero,
        //             delay: This.ShowDelay
        //         });
        // lHeroAvatar.runAction(
        //   cc.sequence(
        //     cc.delayTime(this._showDelay),
        //     cc.moveTo(
        //       CBRConfigDelay.PosHero,
        //       tx + Center.x + 50,
        //       ty + Center.y - 20,
        //     ),
        //   ),
        // );
        lProfile.addChild(lHeroName);
        lProfile.addChild(lHeroAvatar);
        window.lProfile = lProfile;
    }
    AddPlayerProf(Center) {
        let ty = 125;
        let tx = -75;
        if (this._HeroIndex <= Hero_Enum_1.EHeroPlaceInFightRound.DEF_3) {
            tx = -48;
            ty = -138;
        }
        const lHeroData = BRHero_Service_1.BRHeroService.GetHeroData(this._Hero?.idHero ?? 0);
        const lPlayer = BRPlayer_Service_1.BRPlayerService.GetPlayerData(lHeroData?.Hero.id_player ?? -1);
        const lPlayerName = new cc.LabelTTF();
        lPlayerName.setPosition(cc.winSize.width / 2, cc.winSize.height + 50);
        lPlayerName.setString(lPlayer?.Player?.name ?? "النظام");
        lPlayerName.setFontSize(14);
        lPlayerName.setFontFillColor(cc.color(255, 255, 255));
        lPlayerName.enableStroke(cc.color(0, 0, 0), 3);
        lPlayerName.setHorizontalAlignment(cc.TEXT_ALIGNMENT_CENTER);
        lPlayerName.setLocalZOrder(10);
        if (lPlayer?.Player.avatar) {
            const lPlayerAvatar = new ccui.ImageView();
            lPlayerAvatar.setPosition(cc.winSize.width / 2, cc.winSize.height + 50);
            lPlayerAvatar.loadTexture(Hero_Config_1.CHeroConfig[lPlayer.Player.avatar].Icon, ccui.Widget.LOCAL_TEXTURE);
            lPlayerAvatar.setLocalZOrder(10);
            this._PlayerProfile.addChild(lPlayerAvatar);
            lPlayerAvatar.runAction(cc.sequence(cc.delayTime(/*this._showDelay*/ 0), cc.moveTo(Base_1.CBRConfigDelay.PosHero, tx + Center.x + 50, ty + Center.y - 20)));
        }
        lPlayerName.runAction(cc.sequence(cc.delayTime(/*this._showDelay*/ 0), cc.moveTo(Base_1.CBRConfigDelay.PosHero, tx + Center.x, ty + Center.y)));
        this._PlayerProfile.addChild(lPlayerName);
    }
}
exports.BRHero = BRHero;


/***/ },

/***/ "./Src/BR/BRHeroCell.ts"
/*!******************************!*\
  !*** ./Src/BR/BRHeroCell.ts ***!
  \******************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.BRHeroCell = void 0;
const XTranslate_1 = __webpack_require__(/*! ../../../Client/Base/Translate/XTranslate */ "../Client/Base/Translate/XTranslate.ts");
const Battle_Enum_1 = __webpack_require__(/*! ../../../Common/Battle/Battle.Enum */ "../Common/Battle/Battle.Enum.ts");
const Army_Enum_1 = __webpack_require__(/*! ../../../Common/Enum/Army.Enum */ "../Common/Enum/Army.Enum.ts");
const Preload_1 = __webpack_require__(/*! ../Scene/Preload */ "./Src/Scene/Preload.ts");
const Base_1 = __webpack_require__(/*! ./Base */ "./Src/BR/Base.ts");
const BRSoldier_1 = __webpack_require__(/*! ./BRSoldier */ "./Src/BR/BRSoldier.ts");
const Tag_Soldier = 1000;
class BRHeroCell extends ccui.Widget {
    constructor() {
        super(...arguments);
        this._deadAmount = 0;
        this._SolList = [];
    }
    static Create(BlockData, CellData, Hero) {
        const lCell = new BRHeroCell();
        lCell._HeroIndex = CellData.HeroIndex;
        lCell._BlockIndex = CellData.BlockIndex;
        lCell.setName("HeroCell." + lCell._HeroIndex + "." + lCell._BlockIndex);
        lCell._ArmySide = CellData.side;
        lCell._Hero = Hero;
        lCell._BlockData = {
            ...BlockData,
            OriginalCount: BlockData.unit,
        };
        return lCell;
    }
    blockCountColor() {
        if (this._BlockData.unit <= 999)
            return cc.color(255, 255, 255);
        if (this._BlockData.unit <= 9999)
            return cc.color(167, 227, 40);
        if (this._BlockData.unit <= 99999)
            return cc.color(0, 255, 255);
        return cc.color(170, 0, 170);
    }
    AddLargeBlock() {
        if (this._BlockData.unit <= 0)
            return this;
        let lMapWidth = 5;
        let lMapHeight = 4;
        const lTileWidthHalf = 10;
        const lTileHeightHalf = 5;
        const lArmyType = this._BlockData.armyType;
        if ([Army_Enum_1.EArmyType.army_b, Army_Enum_1.EArmyType.army_e, Army_Enum_1.EArmyType.army_f].includes(lArmyType)) {
            lMapWidth = 4;
            lMapHeight = 3;
        }
        for (let y = 0; y < lMapHeight; y++) {
            for (let x = 0; x < lMapWidth; x++) {
                const lTx = (x - y) * lTileWidthHalf;
                const lTy = (x + y) * lTileHeightHalf;
                const lSprite = BRSoldier_1.BRSoldier.Create(this._BlockData.armyType, this._ArmySide);
                lSprite.setLocalZOrder(lTy);
                lSprite.setPosition(lTx, 50);
                lSprite.setTag(Tag_Soldier);
                this.addChild(lSprite);
                lSprite.setPosition(lTx, -lTy);
                this._SolList.push(lSprite);
            }
        }
        const lArmyCount = new ccui.Text(this._BlockData.unit.toString(), "Arial", 14);
        lArmyCount.setColor(this.blockCountColor());
        lArmyCount.enableOutline(cc.color(0, 0, 0), 2);
        this.addChild(lArmyCount, 100);
        this._ArmyAmount = lArmyCount;
        const lDeadArmyCount = new ccui.Text("0", "Arial", 12);
        lDeadArmyCount.setColor(cc.color(255, 255, 255));
        lDeadArmyCount.enableOutline(cc.color(255, 0, 0), 1);
        lDeadArmyCount.setPosition(25, -15);
        lDeadArmyCount.setVisible(false);
        this.addChild(lDeadArmyCount, 100);
        this._DeadArmyAmount = lDeadArmyCount;
        return this;
    }
    GetSoldierAnimation(AttackState) {
        this._SolList.forEach((lSol) => {
            lSol.Animation(AttackState);
        });
    }
    Kill(Amount) {
        this._BlockData.unit = Math.max(this._BlockData.unit - Amount, 0);
        this._ArmyAmount.setString(this._BlockData.unit.toString());
        this._ArmyAmount.setColor(this.blockCountColor());
        this._deadAmount = Math.min(this._deadAmount + Amount, this._BlockData.OriginalCount);
        this._DeadArmyAmount.setString(`-${this._deadAmount}`);
        this._DeadArmyAmount.setVisible(true);
    }
    KillFromCell(amount) {
        const lText = new ccui.Text(amount.toString(), "Arial", 14);
        lText.setString(amount.toString());
        lText.setColor(cc.color(255, 255, 255));
        lText.enableOutline(cc.color(255, 0, 0), 1);
        this._killAnimation(lText, amount);
        return lText;
    }
    _killAnimation(txt, amount) {
        this.addChild(txt, 100);
        txt.runAction(cc.sequence(cc.callFunc(() => {
            this.Kill(amount);
        }), cc.spawn(cc.moveBy(Base_1.CBRConfigDelay.Base, 0, 50), cc.fadeTo(Base_1.CBRConfigDelay.Base, 255)), cc.callFunc(() => {
            txt.removeFromParent();
        })));
    }
    KillWithBreak(Amount) {
        const lText = new cc.LabelBMFont(Amount.toString(), "/BRAsset/Assets/Fonts/desyrel.fnt");
        lText.setString(Amount.toString());
        lText.setScale(0.35);
        this._killAnimation(lText, Amount);
        return lText;
    }
    KillWithStrikeAndBreak(Amount) {
        const lText = new cc.LabelBMFont(Amount.toString(), "/BRAsset/Assets/Fonts/desyrel-pink.fnt");
        lText.setString(Amount.toString());
        lText.setScale(0.35);
        lText.setColor(cc.color(255, 165, 0));
        this._killAnimation(lText, Amount);
        return lText;
    }
    KillWithStrike(Amount) {
        const lText = new cc.LabelBMFont(Amount.toString(), "/BRAsset/Assets/Fonts/desyrel-pink.fnt");
        lText.setString(Amount.toString());
        lText.setScale(0.35);
        this._killAnimation(lText, Amount);
        return lText;
    }
    startAttack(HeroIndex, CellIndex, AttackData) {
        return new Promise((resolve) => {
            const lAttack = new cc.Sprite(`#Weapon.${Preload_1.CArmyTypsWeapons[this._BlockData.armyType]}.png`);
            lAttack.setLocalZOrder(1000);
            lAttack.setVisible(true);
            lAttack.setPosition(30, 10);
            lAttack.setScale(0.3);
            this.addChild(lAttack);
            const lTargetCell = (0, Base_1.GetDeepChild)(cc.director.getRunningScene(), `HeroCell.${HeroIndex}.${CellIndex}`);
            this._ShowAttackText(AttackData.attackSkillType);
            const lMyPos = this.convertToWorldSpaceAR(cc.p(30, 10));
            const lTargetPos = lTargetCell?.convertToWorldSpaceAR(cc.p(0, 0));
            if (lTargetPos) {
                const lMoveAction = cc.moveBy(Base_1.CBRConfigDelay.Attack, lTargetPos.x - lMyPos.x, lTargetPos.y - lMyPos.y);
                lAttack.runAction(cc.spawn(lMoveAction, cc.sequence(cc.scaleTo(Base_1.CBRConfigDelay.Attack / 3, 1), cc.delayTime(Base_1.CBRConfigDelay.Attack / 3), cc.scaleTo(Base_1.CBRConfigDelay.Attack / 3, 0.3), cc.callFunc(() => {
                    lTargetCell?.finishAttack(AttackData);
                }), cc.delayTime(0.5), cc.callFunc(() => {
                    lAttack.removeFromParent();
                    resolve(true);
                }))));
            }
            this.GetSoldierAnimation(Battle_Enum_1.ESoldierStateInBattle.Attack);
        });
    }
    // "scattering.name": "تشتت",
    // "rush.name": "الاجتياح",
    // "sweeparmy.name": "الابادة",
    // "sideinjure.name": "طلعات الاغارة",
    // "perforation.name": "تسلل",
    // "tozt.name": "كرات النار",
    // "longrangestrike.name": "الصواريخ",
    _ShowAttackText(attackSkillType) {
        let ltext = "Normal Attack";
        switch (attackSkillType) {
            case Battle_Enum_1.EAttackHitSkillType.Archer_RainOfArrows:
                ltext = "وايل أمامى";
                break;
            case Battle_Enum_1.EAttackHitSkillType.Archer_RainOfArrowsBack:
                ltext = "وايل خلفى";
                break;
            case Battle_Enum_1.EAttackHitSkillType.Archer_Dispersion:
                ltext = XTranslate_1.XTranslate.i18n("scattering.name");
                break;
            case Battle_Enum_1.EAttackHitSkillType.Hastatus_Missile:
                ltext = XTranslate_1.XTranslate.i18n("longrangestrike.name");
                break;
            case Battle_Enum_1.EAttackHitSkillType.Hastatus_Piercing:
                ltext = XTranslate_1.XTranslate.i18n("perforation.name");
                break;
            case Battle_Enum_1.EAttackHitSkillType.Cavalry_AmbushAssault:
                ltext = XTranslate_1.XTranslate.i18n("sideinjure.name");
                break;
            case Battle_Enum_1.EAttackHitSkillType.Cavalry_AnnihilationSweep:
                ltext = XTranslate_1.XTranslate.i18n("sweeparmy.name");
                break;
            case Battle_Enum_1.EAttackHitSkillType.Cavalry_FlankAssault:
                ltext = XTranslate_1.XTranslate.i18n("rush.name");
                break;
            case Battle_Enum_1.EAttackHitSkillType.Onager_FireBalls:
                ltext = XTranslate_1.XTranslate.i18n("tozt.name");
                break;
            case Battle_Enum_1.EAttackHitSkillType.Principes_Missile:
                ltext = XTranslate_1.XTranslate.i18n("longrangestrike.name");
                break;
            case Battle_Enum_1.EAttackHitSkillType.Ballistae_Perforation:
                ltext = XTranslate_1.XTranslate.i18n("perforation.name");
                break;
            default:
                ltext = XTranslate_1.XTranslate.i18n("ضربة عادية");
                ltext = "";
        }
        const lAttackText = new ccui.Text(ltext, "Arial", 10);
        lAttackText.setColor(cc.color(0, 190, 0));
        lAttackText.enableOutline(cc.color(255, 0, 0), 1);
        this.addChild(lAttackText, 100);
        lAttackText.setPosition(30, 10);
        lAttackText.enableOutline(cc.color(0, 0, 0), 1);
        const lMoveAction = cc.moveBy(Base_1.CBRConfigDelay.Attack, 0, 50);
        lAttackText.runAction(cc.sequence(cc.spawn(lMoveAction, cc.fadeTo(Base_1.CBRConfigDelay.Attack, 255)), cc.callFunc(() => {
            lAttackText.removeFromParent();
        })));
    }
    finishAttack(AttackData) {
        this.AttackHit();
        if (AttackData.attackType == Battle_Enum_1.EAttackHitType.Norm) {
            this.KillFromCell(AttackData.AmountDead);
        }
        else if (AttackData.attackType == Battle_Enum_1.EAttackHitType.Strike) {
            this.KillWithStrike(AttackData.AmountDead);
        }
        else if (AttackData.attackType == Battle_Enum_1.EAttackHitType.Break) {
            this.KillWithBreak(AttackData.AmountDead);
        }
        else if (AttackData.attackType == Battle_Enum_1.EAttackHitType.StrikeAndBreak) {
            this.KillWithStrikeAndBreak(AttackData.AmountDead);
        }
    }
    AttackHit() {
        this._SolList.forEach((lSol) => {
            lSol.AttackHit();
        });
    }
    RemoveBlock() {
        this.getChildren().forEach((Unit) => {
            if (Unit instanceof BRSoldier_1.BRSoldier) {
                Unit.stopAllActions();
                Unit.runAction(cc.sequence(cc.fadeOut(Math.random() * 2), cc.callFunc(() => Unit.removeFromParent())));
            }
            this.runAction(cc.sequence(cc.delayTime(2), cc.callFunc(() => this.removeFromParent())));
        });
    }
    BlockDead() {
        const lSols = this.children;
        const lSolToDieNode = lSols.filter((lSol) => lSol instanceof BRSoldier_1.BRSoldier);
        const lSolToDie = Math.floor(Math.min((this._BlockData.OriginalCount - this._BlockData.unit) /
            this._BlockData.OriginalCount, 1) * lSolToDieNode.length);
        for (let i = 0; i < lSolToDie; i++) {
            const lSol = lSolToDieNode[i];
            lSol.stopAllActions();
            lSol.Die();
        }
    }
}
exports.BRHeroCell = BRHeroCell;


/***/ },

/***/ "./Src/BR/BRPlayer.Service.ts"
/*!************************************!*\
  !*** ./Src/BR/BRPlayer.Service.ts ***!
  \************************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.BRPlayerService = void 0;
const BattleReplay_Service_1 = __webpack_require__(/*! ../BattleReplay.Service */ "./Src/BattleReplay.Service.ts");
class BRPlayerService {
    static GetPlayerData(idPlayer) {
        return Object.values(BattleReplay_Service_1.BattleReplayService.BattelReplayData.player_list).find((p) => p.Player.id_player === idPlayer);
    }
}
exports.BRPlayerService = BRPlayerService;


/***/ },

/***/ "./Src/BR/BRRound.ts"
/*!***************************!*\
  !*** ./Src/BR/BRRound.ts ***!
  \***************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.BRRound = void 0;
const Hero_Enum_1 = __webpack_require__(/*! ../../../Common/Hero/Hero.Enum */ "../Common/Hero/Hero.Enum.ts");
const XLog_1 = __webpack_require__(/*! ../../../Common/XLog */ "../Common/XLog.ts");
const BattleReplay_Service_1 = __webpack_require__(/*! ../BattleReplay.Service */ "./Src/BattleReplay.Service.ts");
const BRHero_1 = __webpack_require__(/*! ./BRHero */ "./Src/BR/BRHero.ts");
class BRRound extends ccui.Widget {
    constructor() {
        super(...arguments);
        this._currentRoundIndex = 0;
        this._currentAttackIndex = -1;
        this._autoPlay = true;
        this._cancelAutoPlay = false;
        this._callBackCalled = false;
        this._HeroList = new Map();
    }
    static Create() {
        const lRound = new BRRound();
        lRound._AttackSide = new cc.Layer();
        lRound._DefSide = new cc.Layer();
        lRound._DefSide.setPosition(350, 320);
        lRound._AttackSide.setPosition(150, 200);
        lRound._AttackSide.setScale(1, 1);
        lRound._DefSide.setScale(1, 1);
        lRound.addChild(lRound._AttackSide, 15);
        lRound.addChild(lRound._DefSide, 15);
        return lRound;
    }
    SetRound(RoundIndex) {
        this._RoundData =
            BattleReplay_Service_1.BattleReplayService.BattelReplayData.round_list[RoundIndex];
        if (!this._RoundData) {
            XLog_1.XLog.Error("No Round Data");
            return;
        }
        this._currentRoundIndex = RoundIndex;
    }
    async _PlaceHero(lHeroIndex) {
        const heroData = this._RoundData.Heros[lHeroIndex];
        if (!heroData) {
            return;
        }
        const existingHero = this._HeroList.get(lHeroIndex);
        if (existingHero && existingHero._Hero?.idHero === heroData.idHero) {
            return;
        }
        const lHero = BRHero_1.BRHero.Create(heroData, this);
        if (!lHero) {
            return;
        }
        const lOffset = cc.p(100, 150);
        if (lHeroIndex === Hero_Enum_1.EHeroPlaceInFightRound.DEF_1) {
            lHero.setPosition(-this._DefSide.x + lOffset.x, -this._DefSide.y + lOffset.y);
            this._DefSide.addChild(lHero);
            await lHero.PlaceAt(0, 200);
        }
        else if (lHeroIndex === Hero_Enum_1.EHeroPlaceInFightRound.DEF_2) {
            lHero.setPosition(-this._DefSide.x + lOffset.x, -this._DefSide.y + lOffset.y);
            this._DefSide.addChild(lHero);
            await lHero.PlaceAt(205, 120);
        }
        else if (lHeroIndex === Hero_Enum_1.EHeroPlaceInFightRound.DEF_3) {
            lHero.setPosition(-this._DefSide.x + lOffset.x, -this._DefSide.y + lOffset.y);
            this._DefSide.addChild(lHero);
            await lHero.PlaceAt(345, 5);
        }
        else if (lHeroIndex === Hero_Enum_1.EHeroPlaceInFightRound.ATT_1) {
            lHero.setPosition(-this._AttackSide.x + lOffset.x, -this._AttackSide.y + lOffset.y);
            this._AttackSide.addChild(lHero);
            await lHero.PlaceAt(0, 200);
        }
        else if (lHeroIndex === Hero_Enum_1.EHeroPlaceInFightRound.ATT_2) {
            lHero.setPosition(-this._AttackSide.x + lOffset.x, -this._AttackSide.y + lOffset.y);
            this._AttackSide.addChild(lHero);
            await lHero.PlaceAt(205, 120);
        }
        else if (lHeroIndex === Hero_Enum_1.EHeroPlaceInFightRound.ATT_3) {
            lHero.setPosition(-this._AttackSide.x + lOffset.x, -this._AttackSide.y + lOffset.y);
            this._AttackSide.addChild(lHero);
            await lHero.PlaceAt(345, 5);
        }
        this._HeroList.set(lHeroIndex, lHero);
    }
    async StartNewRound() {
        for (const iii in this._RoundData.Heros) {
            const lHeroIndex = Number(iii);
            await this._PlaceHero(lHeroIndex);
        }
        await this.StartRoundAttacks();
    }
    SweapHeros() {
        this._HeroList.forEach((Hero) => {
            const lEmptyHero = Hero.SweapHero();
            if (lEmptyHero) {
                this._HeroList.delete(Hero._HeroIndex);
            }
        });
    }
    async _ExecuteAttackAt(index) {
        const Attack = this._RoundData.Attacks[index];
        const lAttackBlock = this._HeroList
            .get(Attack.HeroAttIndex)
            ?._HeroCells.get(Attack.CellAttIndex);
        await lAttackBlock?.startAttack(Attack.HeroDefIndex, Attack.CellDefIndex, {
            attackType: Attack.AttackType,
            AmountDead: Attack.totalDead,
            attackSkillType: Attack.hitSkillType,
        });
    }
    _ApplyAttackInstantAt(index) {
        const Attack = this._RoundData.Attacks[index];
        const lDefCell = this._HeroList
            .get(Attack.HeroDefIndex)
            ?._HeroCells.get(Attack.CellDefIndex);
        lDefCell?.Kill(Attack.totalDead);
    }
    async _RebuildHeroes() {
        this._HeroList.forEach((hero) => hero.removeFromParent());
        this._HeroList.clear();
        for (const iii in this._RoundData.Heros) {
            const lHeroIndex = Number(iii);
            await this._PlaceHero(lHeroIndex);
        }
        // wait one frame for onEnter to fire and populate _HeroCells
        await new Promise((r) => setTimeout(r, 0));
    }
    async StepForward() {
        const nextIndex = this._currentAttackIndex + 1;
        if (nextIndex >= this._RoundData.Attacks.length)
            return false;
        this._currentAttackIndex = nextIndex;
        await this._ExecuteAttackAt(nextIndex);
        return true;
    }
    async StepBackward() {
        if (this._currentAttackIndex < 0)
            return false;
        const targetIndex = this._currentAttackIndex - 1;
        await this._RebuildHeroes();
        for (let i = 0; i <= targetIndex; i++) {
            this._ApplyAttackInstantAt(i);
        }
        this._currentAttackIndex = targetIndex;
        return true;
    }
    StopAutoPlay() {
        this._cancelAutoPlay = true;
    }
    async StartRoundAttacks(fromIndex = 0) {
        const Attacks = this._RoundData.Attacks;
        for (let i = fromIndex; i < Attacks.length; i++) {
            if (this._cancelAutoPlay)
                return;
            this._currentAttackIndex = i;
            await this._ExecuteAttackAt(i);
        }
        await this.StartNextRound();
    }
    async StartNextRound() {
        XLog_1.XLog.log("Round " + this._currentRoundIndex + " is finished");
        this.SweapHeros();
        this._callBackCalled = false;
    }
}
exports.BRRound = BRRound;


/***/ },

/***/ "./Src/BR/BRSoldier.ts"
/*!*****************************!*\
  !*** ./Src/BR/BRSoldier.ts ***!
  \*****************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.BRSoldier = void 0;
const Battle_Enum_1 = __webpack_require__(/*! ../../../Common/Battle/Battle.Enum */ "../Common/Battle/Battle.Enum.ts");
const Preload_1 = __webpack_require__(/*! ../Scene/Preload */ "./Src/Scene/Preload.ts");
class BRSoldier extends cc.Sprite {
    constructor() {
        super(...arguments);
        this._hitSprite = null;
        this._IdleAction = null;
        this._TakeHitAction = null;
    }
    static Create(armyType, side) {
        const lFrame = Preload_1.CTroopsAnimation[armyType].Key.replace("{Key}", Battle_Enum_1.ESoldierStateInBattle.Idle)
            .replace("{Side}", side.toString())
            .replace("{FrameIndex}", "0");
        const sol = new BRSoldier(`#${lFrame}`);
        sol._ArmyType = armyType;
        sol._Side = side;
        return sol;
    }
    Animation(attackState) {
        const lFrame = Preload_1.CTroopsAnimation[this._ArmyType].Key.replace("{Key}", attackState).replace("{Side}", this._Side.toString());
        const lFrames = [];
        let lEnd = 3;
        let lDelay = 0.2;
        if (attackState === Battle_Enum_1.ESoldierStateInBattle.Attack) {
            lEnd = 9;
            lDelay = 0.05;
        }
        for (let i = 0; i <= lEnd; i++) {
            lFrames.push(cc.spriteFrameCache.getSpriteFrame(lFrame.replace("{FrameIndex}", i.toString())));
        }
        if (attackState === Battle_Enum_1.ESoldierStateInBattle.Idle) {
            const lAnimation = new cc.Animation(lFrames, lDelay);
            const lAnimate = cc.animate(lAnimation);
            this.stopAllActions();
            this._IdleAction = this.runAction(cc.repeatForever(lAnimate));
        }
        else {
            const lAnimation = new cc.Animation(lFrames, lDelay);
            const lAnimate = cc.animate(lAnimation);
            this.stopAllActions();
            this._IdleAction = this.runAction(cc.sequence(lAnimate, cc.callFunc(() => {
                this.Animation(Battle_Enum_1.ESoldierStateInBattle.Idle);
            })));
        }
    }
    Die() {
        this.setSpriteFrame(Preload_1.CTroopsAnimation[this._ArmyType].Dead.replace("{Side}", this._Side.toString()));
    }
    AttackHit() {
        const lFrame = "killHit.{FrameIndex}.png";
        const lFrames = [];
        const lActinArr = [];
        for (let i = 0; i <= 4; i++) {
            lFrames.push(cc.spriteFrameCache.getSpriteFrame(lFrame.replace("{FrameIndex}", i.toString())));
            lActinArr.push(cc.sequence(cc.callFunc(() => {
                this._hitSprite?.setSpriteFrame(lFrames[i]);
            }), cc.delayTime(0.1)));
        }
        lActinArr.concat(cc.callFunc(() => {
            this._hitSprite?.removeFromParent();
            this._hitSprite = null;
        }));
        const lAnimate = cc.sequence(...lActinArr);
        const lSprite = new cc.Sprite(`#${lFrame.replace("{FrameIndex}", "0")}`);
        this._hitSprite = lSprite;
        lSprite.setPosition(this.getContentSize().width / 2, this.getContentSize().height / 2);
        this.addChild(lSprite);
        lSprite.setLocalZOrder(this._Side == Battle_Enum_1.EBattleSide.Attack ? -10 : 10);
        this._TakeHitAction = lSprite.runAction(lAnimate);
    }
    Stop() {
        if (this._IdleAction) {
            this.stopAction(this._IdleAction);
            this._IdleAction = null;
        }
        if (this._TakeHitAction) {
            this._hitSprite?.stopAction(this._TakeHitAction);
            this._TakeHitAction = null;
        }
    }
    Pause() {
        if (this._IdleAction) {
            this.pause();
        }
        if (this._TakeHitAction) {
            this.pause();
        }
    }
    Resume() {
        if (this._IdleAction) {
            this.resume();
        }
        if (this._TakeHitAction) {
            this.resume();
        }
    }
}
exports.BRSoldier = BRSoldier;


/***/ },

/***/ "./Src/BR/Base.ts"
/*!************************!*\
  !*** ./Src/BR/Base.ts ***!
  \************************/
(__unused_webpack_module, exports) {

"use strict";

// Elkaisar.Config.Delay = {};
// Elkaisar.Config.Delay.Attack      = 1000;
// Elkaisar.Config.Delay.PosHero     = 2000;
// Elkaisar.Config.Delay.Base        = 2000;
// Elkaisar.Config.Delay.HeroShow    = 500;
Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.CBRConfigDelay = void 0;
exports.GetDeepChild = GetDeepChild;
exports.CBRConfigDelay = {
    Attack: 1,
    PosHero: 1,
    Base: 2,
    HeroShow: 0.5,
};
function GetDeepChild(node, name) {
    if (node.getName() == name)
        return node;
    for (const child of node.children) {
        const found = GetDeepChild(child, name);
        if (found)
            return found;
    }
    return null;
}


/***/ },

/***/ "./Src/BattleReplay.Service.ts"
/*!*************************************!*\
  !*** ./Src/BattleReplay.Service.ts ***!
  \*************************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.BattleReplayService = void 0;
const Battle_Enum_1 = __webpack_require__(/*! ../../Common/Battle/Battle.Enum */ "../Common/Battle/Battle.Enum.ts");
const Army_Enum_1 = __webpack_require__(/*! ../../Common/Enum/Army.Enum */ "../Common/Enum/Army.Enum.ts");
const XLog_1 = __webpack_require__(/*! ../../Common/XLog */ "../Common/XLog.ts");
class BattleReplayService {
    static Init(BattelReplayData) {
        if (!BattelReplayData) {
            XLog_1.XLog.Error("BattleReplayService.Init: Invalid Battle Replay Data");
            return;
        }
        this.BattelReplayData = BattelReplayData;
    }
    static _AttackerWin() {
        return BattleReplayService.BattelReplayData.hero_list.some((hero) => {
            const lSide = hero.Hero.side;
            if (lSide !== Battle_Enum_1.EBattleSide.Attack)
                return false;
            if (hero.post[Army_Enum_1.EArmyPlaceOnHero.f_1] > 0) {
                return true;
            }
            else if (hero.post[Army_Enum_1.EArmyPlaceOnHero.f_2] > 0) {
                return true;
            }
            else if (hero.post[Army_Enum_1.EArmyPlaceOnHero.f_3] > 0) {
                return true;
            }
            else if (hero.post[Army_Enum_1.EArmyPlaceOnHero.b_1] > 0) {
                return true;
            }
            else if (hero.post[Army_Enum_1.EArmyPlaceOnHero.b_2] > 0) {
                return true;
            }
            else if (hero.post[Army_Enum_1.EArmyPlaceOnHero.b_3] > 0) {
                return true;
            }
            return false;
        });
    }
    static GetPlayerName(id_player) {
        const lPlayer = Object.values(BattleReplayService.BattelReplayData.player_list).find((player) => player.Player.id_player === id_player);
        return lPlayer?.Player.name || "";
    }
    static getAllPrizeList() {
        const lPrizeList = Object.values(BattleReplayService.BattelReplayData.player_list).reduce((acc, player) => {
            for (const lItem of player.ItemPrize) {
                if (acc[lItem.Item]) {
                    acc[lItem.Item] += lItem.amount;
                }
                else {
                    acc[lItem.Item] = lItem.amount;
                }
            }
            return acc;
        }, {});
        return lPrizeList;
    }
    static getAllResourcePrizeList() {
        const lResourcePrizeList = Object.values(BattleReplayService.BattelReplayData.player_list).reduce((acc, player) => {
            for (const [lResource, lAmount] of Object.entries(player.ResourcePrize)) {
                if (acc[lResource]) {
                    acc[lResource] += lAmount;
                }
                else {
                    acc[lResource] = lAmount;
                }
            }
            return acc;
        }, {});
        return lResourcePrizeList;
    }
}
exports.BattleReplayService = BattleReplayService;


/***/ },

/***/ "./Src/Scene/BattleReplay.Scene.ts"
/*!*****************************************!*\
  !*** ./Src/Scene/BattleReplay.Scene.ts ***!
  \*****************************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.BattleReplayScene = void 0;
const XLog_1 = __webpack_require__(/*! ../../../Common/XLog */ "../Common/XLog.ts");
const BattleReplay_Service_1 = __webpack_require__(/*! ../BattleReplay.Service */ "./Src/BattleReplay.Service.ts");
const BRRound_1 = __webpack_require__(/*! ../BR/BRRound */ "./Src/BR/BRRound.ts");
const UIBoardControl_1 = __webpack_require__(/*! ../UI/UIBoardControl */ "./Src/UI/UIBoardControl.ts");
const UICityDef_1 = __webpack_require__(/*! ../UI/UICityDef */ "./Src/UI/UICityDef.ts");
const UIRoundTitle_1 = __webpack_require__(/*! ../UI/UIRoundTitle */ "./Src/UI/UIRoundTitle.ts");
const UITopBar_1 = __webpack_require__(/*! ../UI/UITopBar */ "./Src/UI/UITopBar.ts");
class BattleReplayScene extends cc.Scene {
    constructor() {
        super(...arguments);
        this._currentRoundIndex = 0;
    }
    static Create() {
        const lScene = new BattleReplayScene();
        lScene.Init();
        window.BattleReplayScene = lScene;
        return lScene;
    }
    _SetBg() {
        const lBgContainer = new cc.Node();
        lBgContainer.setPosition(0, 0);
        lBgContainer.setAnchorPoint(0, 0);
        this.addChild(lBgContainer, 0);
        const lWidth = Math.ceil(cc.winSize.width / 325);
        for (let i = 0; i < lWidth; i++) {
            const lBG = new ccui.ImageView("/BRAsset/Img/BattleReplay/bbgg.jpg");
            lBG.setPosition(i * 325, cc.winSize.height - 1500);
            lBG.setAnchorPoint(0, 0);
            lBG.setGlobalZOrder(0);
            lBgContainer.addChild(lBG, 0);
        }
    }
    Init() {
        this._SetBg();
        const lBaseX = Math.round(cc.winSize.width / 2);
        const lBaseY = Math.round(cc.winSize.height - 300);
        this._BattleFloor = new ccui.Widget();
        this._WindowFloor = new ccui.Widget();
        this._UILayer = new ccui.Widget();
        this._DummyNode = new ccui.Widget();
        this._BattleFloor.setPosition(lBaseX, lBaseY);
        this._WindowFloor.setPosition(lBaseX, lBaseY);
        this._UILayer.setPosition(lBaseX, lBaseY);
        this._DummyNode.setPosition(lBaseX, lBaseY);
        this._BattleFloor.setContentSize(1000, 600);
        this._WindowFloor.setContentSize(1000, 600);
        this._UILayer.setContentSize(1000, 600);
        this._DummyNode.setContentSize(1000, 600);
        this.addChild(this._WindowFloor);
        this.addChild(this._BattleFloor);
        this.addChild(this._DummyNode);
        this.addChild(this._UILayer);
        this._BattleFloor.setGlobalZOrder(1);
        this._WindowFloor.setGlobalZOrder(2);
        this._UILayer.setGlobalZOrder(100);
        this._UILayer.setScale(0.99);
        const lBG = new ccui.ImageView("/BRAsset/Img/background/BackGround.jpeg");
        lBG.setPosition(0, 0);
        lBG.setAnchorPoint(0, 0);
        lBG.setGlobalZOrder(0);
        const lFloor = new ccui.ImageView("/BRAsset/Img/BattleReplay/field.jpg");
        lFloor.setPosition(0, 0);
        lFloor.setAnchorPoint(0, 0);
        this._WindowFloor.addChild(lFloor, 1);
        lBG.setScale(cc.winSize.width / 1280, cc.winSize.height / 720);
        const lFrame = new ccui.ImageView("/BRAsset/Img/BattleReplay/windowFrame.png");
        lFrame.setPosition(0, 0);
        lFrame.setAnchorPoint(0, 0);
        this._WindowFloor.addChild(lFrame, 2);
    }
    EnterScene() {
        this._showTop();
        this._ShowBottom();
        this._ShowCityDef();
    }
    _showTop() {
        const lTopBar = UITopBar_1.UITopBar.Create();
        lTopBar.setPosition(0, 600 - lTopBar.height - 2);
        lTopBar.setAnchorPoint(0, 0);
        this._UILayer.addChild(lTopBar);
    }
    async _ShowBottom() {
        const lBottomBar = await UIBoardControl_1.UIBoardControl.Create();
        lBottomBar.setPosition(0, 0);
        lBottomBar.setAnchorPoint(0, 0);
        this._UILayer.addChild(lBottomBar, 100);
        lBottomBar._OnPauseClick = this.Pause.bind(this);
        lBottomBar._OnPlayClick = this.Resume.bind(this);
        lBottomBar._OnNextClick = () => this.NavigateTo(1);
        lBottomBar._OnPrevClick = () => this.NavigateTo(-1);
    }
    _ShowCityDef() {
        const lCityDef = UICityDef_1.UICityDef.Create();
        lCityDef.setPosition(1000 - 135, 600 - lCityDef.height - 40);
        lCityDef.setAnchorPoint(0, 0);
        this._UILayer.addChild(lCityDef, 100);
        this._CityDef = lCityDef;
    }
    async Start() {
        await this._GoToRound(0);
    }
    async Pause() {
        cc.director.pause();
    }
    async Resume() {
        cc.director.resume();
    }
    async NavigateTo(direction) {
        XLog_1.XLog.log("BattleReplayScene", "NavigateTo", direction);
        this._Round?.StopAutoPlay();
        if (direction === 1) {
            const hasMore = await this._Round?.StepForward();
            if (!hasMore) {
                await this._GoToRound(this._currentRoundIndex + 1);
            }
        }
        else {
            const wentBack = await this._Round?.StepBackward();
            if (!wentBack) {
                await this._GoToRound(this._currentRoundIndex - 1);
            }
        }
    }
    async _GoToRound(roundIndex) {
        if (roundIndex < 0)
            return;
        const rounds = BattleReplay_Service_1.BattleReplayService.BattelReplayData?.round_list;
        if (!rounds || roundIndex >= rounds.length) {
            setTimeout(() => {
                this._CityDef?.ShowReport(null, ccui.Widget.TOUCH_ENDED);
            }, 800);
            return;
        }
        const lRound = this._Round ?? BRRound_1.BRRound.Create();
        if (!lRound)
            return;
        if (this._Round) {
            await this._Round.SweapHeros();
        }
        else {
            this._BattleFloor.addChild(lRound);
        }
        this._currentRoundIndex++;
        this._Round = lRound;
        lRound.SetRound(roundIndex);
        const lRoundTitle = UIRoundTitle_1.UIRoundTitle.Create(this._currentRoundIndex);
        lRoundTitle.setPosition(500, 400);
        lRoundTitle.setAnchorPoint(0.5, 0.5);
        this._UILayer.addChild(lRoundTitle, 100);
        await lRoundTitle.AnimateIn();
        await lRound.StartNewRound();
        this._GoToRound(this._currentRoundIndex);
    }
}
exports.BattleReplayScene = BattleReplayScene;


/***/ },

/***/ "./Src/Scene/Loading.Scene.ts"
/*!************************************!*\
  !*** ./Src/Scene/Loading.Scene.ts ***!
  \************************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.CLoadingScene = void 0;
const jquery_1 = __importDefault(__webpack_require__(/*! jquery */ "./node_modules/jquery/dist/jquery.js"));
const XNet_Rest_1 = __webpack_require__(/*! ../../../Client/Base/Net/XNet.Rest */ "../Client/Base/Net/XNet.Rest.ts");
const XLog_1 = __webpack_require__(/*! ../../../Common/XLog */ "../Common/XLog.ts");
const BattleReplay_Service_1 = __webpack_require__(/*! ../BattleReplay.Service */ "./Src/BattleReplay.Service.ts");
const BattleReplay_Scene_1 = __webpack_require__(/*! ./BattleReplay.Scene */ "./Src/Scene/BattleReplay.Scene.ts");
const XTranslate_1 = __webpack_require__(/*! ../../../Client/Base/Translate/XTranslate */ "../Client/Base/Translate/XTranslate.ts");
const lRes = [
    "/BRAsset/Assets/Fonts/desyrel.fnt",
    "/BRAsset/Assets/Fonts/desyrel.png",
    "/BRAsset/Assets/Fonts/desyrel-pink.fnt",
    "/BRAsset/Assets/Fonts/desyrel-pink.png",
    "/BRAsset/Img/BattleReplay/field.jpg",
    "/BRAsset/Img/BattleReplay/windowFrame.png",
    "/Res/animation/loading.plist",
    "/BRAsset/Img/BattleReplay/troop_archers.plist",
    "/BRAsset/Img/BattleReplay/troop_ballistas.plist",
    "/BRAsset/Img/BattleReplay/troop_cavalry.plist",
    "/BRAsset/Img/BattleReplay/troop_cohort.plist",
    "/BRAsset/Img/BattleReplay/troop_onagers.plist",
    "/BRAsset/Img/BattleReplay/troop_triarii.plist",
    "/BRAsset/Img/BattleReplay/others_weapon.plist",
    "/BRAsset/Img/BattleReplay/others_corpse.plist",
    "/BRAsset/Img/BattleReplay/others_kill.plist",
    "/BRAsset/Img/BattleReplay/BattleControl/bottom.png",
    "/BRAsset/Assets/Fonts/bitmapFontTest2.fnt",
    "/BRAsset/Assets/Fonts/bitmapFontTest2.png    ",
];
/**
 * @Original Elkaisar.GE.LoadingScene
 */
class CLoadingScene extends cc.Scene {
    constructor() {
        super();
        //     _interval : null,
        //     _label : null,
        //     _logo : null,
        //     _className:"LoaderScene",
        //     cb: null,
        //     target: null,
        this._loaded = false;
        this._ProgressBar = null;
        this._ProgressLabel = null;
        this._ProgressLight = null;
        this._currentPercent = 0;
        const container = document.getElementById("Cocos2dGameContainer");
        if (container) {
            container.style.position = "relative";
            container.style.zIndex = "200";
            container.style.backgroundColor = "black";
        }
    }
    EnterScene() { }
    //     /**
    //      * Contructor of cc.LoaderScene
    //      * @returns {boolean}
    //      */
    init() {
        super.init();
        XTranslate_1.XTranslate.Init();
        const lBgLayer = new cc.LayerColor();
        lBgLayer.setColor(cc.color(32, 32, 32, 255));
        this.addChild(lBgLayer, 0);
        const lBgSprite = new cc.Sprite("/Res/style/log-in-bg.jpg");
        lBgSprite.setPosition(cc.winSize.width / 2, cc.winSize.height / 2);
        lBgSprite.setScale(cc.winSize.width / 2999);
        lBgLayer.addChild(lBgSprite, 1);
        const lLogoSprite = new cc.Sprite("/Res/icons/Logo-wow.png");
        lLogoSprite.setPosition(cc.winSize.width / 2, cc.winSize.height - 200);
        lLogoSprite.setScale(0.75);
        lBgLayer.addChild(lLogoSprite, 2);
        const lProgressBg = new cc.Sprite("/Res/Scene/Loading/loading_bar.png");
        lProgressBg.setPosition(cc.winSize.width / 2, 250);
        this.addChild(lProgressBg, 3);
        this._ProgressBar = new ccui.LoadingBar();
        this._ProgressBar.loadTexture("/Res/Scene/Loading/loading_progress.png", ccui.Widget.LOCAL_TEXTURE);
        this._ProgressBar.setDirection(ccui.LoadingBar.TYPE_LEFT);
        this._ProgressBar.setPercent(0);
        this._ProgressBar.setPosition(30, 5);
        this._ProgressBar.setAnchorPoint(0, 0);
        lProgressBg.addChild(this._ProgressBar, 4);
        lProgressBg.setScale(1.5);
        this._ProgressLabel = new cc.LabelTTF("0%", "Arial", 24);
        this._ProgressLabel.setPosition(cc.winSize.width / 2, 200);
        this._ProgressLabel.setColor(cc.color(255, 255, 255));
        this._ProgressLabel.enableStroke(cc.color(0, 0, 0), 2);
        this._ProgressLabel.setAnchorPoint(0.5, 0.5);
        this.addChild(this._ProgressLabel, 4);
        this._ProgressLight = new cc.Sprite("/Res/Scene/Loading/Loading_bar_glow.png");
        this._ProgressLight.setAnchorPoint(0.5, 0.5);
        this._ProgressLight.setPosition(0, 4);
        this._ProgressBar.addChild(this._ProgressLight, 5);
        // $("#loader-layer").remove();
        //         var logoWidth = 160;
        //         var logoHeight = 200;
        //         // bg
        //         var bgLayer = self._bgLayer = new cc.LayerColor(cc.color(32, 32, 32, 255));
        //         self.addChild(bgLayer, 0);
        //         //image move to CCSceneFile.js
        //         var fontSize = 24, lblHeight =  -logoHeight / 2 + 100;
        //         if(cc._loaderImage){
        //             //loading logo
        //             // cc.loader.loadImg(cc._loaderImage, {isCrossOrigin : false }, function(err, img){
        //             //     logoWidth = img.width;
        //             //     logoHeight = img.height;
        //             //     self._initStage(img, cc.visibleRect.center);
        //             // });
        //             fontSize = 14;
        //             lblHeight = -logoHeight / 2 - 10;
        //         }
        //         //loading percent
        //         var label = self._label = new cc.LabelTTF("Loading... 0%", "Arial", fontSize);
        //         label.setPosition(cc.pAdd(cc.visibleRect.center, cc.p(0, lblHeight)));
        //         label.setColor(cc.color(180, 180, 180));
        //         bgLayer.addChild(this._label, 10);
        return true;
    }
    //     _initStage: function (img, centerPos) {
    //         var self = this;
    //         var texture2d = self._texture2d = new cc.Texture2D();
    //         texture2d.initWithElement(img);
    //         texture2d.handleLoadedTexture();
    //         var logo = self._logo = new cc.Sprite(texture2d);
    //         logo.setScale(cc.contentScaleFactor());
    //         logo.x = centerPos.x;
    //         logo.y = centerPos.y;
    //         self._bgLayer.addChild(logo, 10);
    //     },
    //     /**
    //      * custom onEnter
    //      */
    onEnter() {
        super.onEnter();
        this.schedule(this._startLoading.bind(this), 0, 0, 0.3);
    }
    //     /**
    //      * custom onExit
    //      */
    //     onExit: function () {
    //         cc.Node.prototype.onExit.call(this);
    //         var tmpStr = "Loading... 0%";
    //         this._label.setString(tmpStr);
    //     },
    //     /**
    //      * init with resources
    //      * @param {Array} resources
    //      * @param {Function|String} cb
    //      * @param {Object} target
    //      */
    //     initWithResources: function (resources, cb, target) {
    //         if(cc.isString(resources))
    //             resources = [resources];
    //         this.resources = resources || [];
    //         this.cb = cb;
    //         this.target = target;
    //     },
    _startLoading() {
        this.unschedule(this._startLoading);
        //         var res = self.resources;
        cc.loader.load(lRes, (result, count, loadedCount) => {
            var percent = ((loadedCount / count) * 100) | 0;
            percent = Math.min(percent, 100);
            // self._label.setString("Loading... " + percent + "%");
            this.Loading(percent);
        }, this.Loaded.bind(this));
    }
    async Loaded() {
        if (this._loaded) {
            XLog_1.XLog.Warn("LoadingScene already loaded, skipping");
            return;
        }
        this._loaded = true;
        this._ProgressBar?.removeFromParent();
        this._ProgressLabel?.removeFromParent();
        this._ProgressLight?.removeFromParent();
        XLog_1.XLog.log("LoadingScene Loaded Resources");
        lRes.forEach((res) => {
            if (!res.endsWith(".plist"))
                return;
            cc.spriteFrameCache.addSpriteFrames(res);
            XLog_1.XLog.log(`Loaded ${res}`);
        });
        const lSprite = new cc.Sprite("#loading_0_0.png");
        const lSpriteSheet = [];
        for (let i = 0; i <= 6; i++) {
            for (let j = 0; j <= 7; j++) {
                lSpriteSheet.push(cc.spriteFrameCache.getSpriteFrame(`loading_${j}_${i}.png`));
            }
        }
        // remove last 4 elements
        lSpriteSheet.splice(-7, 7);
        const lAnimation = new cc.Animation(lSpriteSheet, 0.05);
        const lAnimateAction = cc.animate(lAnimation).repeatForever();
        lSprite.runAction(lAnimateAction);
        lSprite.setPosition(cc.winSize.width / 2, cc.winSize.height - 400);
        // TODO: add to a dedicated loading layer and remove after loading is done
        this.addChild(lSprite, 3);
        this._ProgressBar?.setPercent(100);
        this._ProgressLabel?.setString("100%");
        this._ProgressLight?.setPositionX(this._ProgressBar?.width ?? 0);
        const lReplayID = window.location.href.split("/").pop() || "InValid";
        const battleReplay = await XNet_Rest_1.XNetRest.Get("/GetBattle", {
            ReplayID: lReplayID,
        });
        XLog_1.XLog.log("Fetched Battle Replay Data:", battleReplay);
        BattleReplay_Service_1.BattleReplayService.Init(battleReplay?.BattleReplay);
        // BattleReplayService.Init(lSampleBattle);
        const lScene = BattleReplay_Scene_1.BattleReplayScene.Create();
        cc.director.runScene(lScene);
        lScene.EnterScene();
        (0, jquery_1.default)("#loader-layer").hide();
        lScene.Start();
        // XSound.PlayEffect(ESoundClass.WarStart);
    }
    //     _updateTransform: function(){
    //         this._renderCmd.setDirtyFlag(cc.Node._dirtyFlags.transformDirty);
    //         this._bgLayer._renderCmd.setDirtyFlag(cc.Node._dirtyFlags.transformDirty);
    //         this._label._renderCmd.setDirtyFlag(cc.Node._dirtyFlags.transformDirty);
    //         this._logo && this._logo._renderCmd.setDirtyFlag(cc.Node._dirtyFlags.transformDirty);
    //     }
    // });
    // /**
    //  * <p>cc.LoaderScene.preload can present a loaderScene with download progress.</p>
    //  * <p>when all the resource are downloaded it will invoke call function</p>
    //  * @param resources
    //  * @param cb
    //  * @param target
    //  * @returns {cc.LoaderScene|*}
    //  * @example
    //  * //Example
    //  * cc.LoaderScene.preload(g_resources, function () {
    //         cc.director.runScene(new HelloWorldScene());
    //     }, this);
    //  */
    // cc.LoaderScene.preload = function(resources, cb, target){
    //     var _cc = cc;
    //     if(!_cc.loaderScene) {
    //         _cc.loaderScene = new cc.LoaderScene();
    //         _cc.loaderScene.init();
    //     }
    //     return _cc.loaderScene;
    // };
    static Create() {
        const lScene = new CLoadingScene();
        lScene.init();
        //         cc.eventManager.addCustomListener(cc.Director.EVENT_PROJECTION_CHANGED, function(){
        //             _cc.loaderScene._updateTransform();
        //         });
        //     _cc.loaderScene.initWithResources(resources, cb, target);
        cc.director.runScene(lScene);
        return lScene;
    }
    Loading(percent) {
        // $("#load-percent").html(Math.floor(percent) + "%");
        // if (percent < this._currentPercent) return;
        // this._currentPercent = percent;
        // this._ProgressBar?.setPercent(percent);
        // this._ProgressLabel?.setString(Math.floor(percent) + "%");
        // this._ProgressLight?.setPositionX(
        //   (this._ProgressBar?.width ?? 0) * (percent / 100),
        // );
        // $("#load-bar div").css({ width: percent + "%" });
        if (percent >= 100) {
            this.Loaded();
        }
    }
}
exports.CLoadingScene = CLoadingScene;
CLoadingScene.Scene = "LoadingScene";


/***/ },

/***/ "./Src/Scene/Preload.ts"
/*!******************************!*\
  !*** ./Src/Scene/Preload.ts ***!
  \******************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

//Crafty.init();
Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.CTroopsAnimation = exports.CDeadSol = exports.CArmyTypsWeapons = void 0;
const Battle_Enum_1 = __webpack_require__(/*! ../../../Common/Battle/Battle.Enum */ "../Common/Battle/Battle.Enum.ts");
const Army_Enum_1 = __webpack_require__(/*! ../../../Common/Enum/Army.Enum */ "../Common/Enum/Army.Enum.ts");
// Elkaisar.BattelReplay.ArmyTyps = {
//     ArmyA: 1,
//     ArmyB: 2,
//     ArmyC: 3,
//     ArmyD: 4,
//     ArmyE: 5,
//     ArmyF: 6
// };
// Elkaisar.BattelReplay.ArmyTypsWeapons = {
//     [Elkaisar.BattelReplay.ArmyTyps.ArmyA]: 1,
//     [Elkaisar.BattelReplay.ArmyTyps.ArmyB]: 5,
//     [Elkaisar.BattelReplay.ArmyTyps.ArmyC]: 2,
//     [Elkaisar.BattelReplay.ArmyTyps.ArmyD]: 0,
//     [Elkaisar.BattelReplay.ArmyTyps.ArmyE]: 2,
//     [Elkaisar.BattelReplay.ArmyTyps.ArmyF]: 3
// };
exports.CArmyTypsWeapons = {
    [Army_Enum_1.EArmyType.army_a]: 1,
    [Army_Enum_1.EArmyType.army_b]: 5,
    [Army_Enum_1.EArmyType.army_c]: 2,
    [Army_Enum_1.EArmyType.army_d]: 0,
    [Army_Enum_1.EArmyType.army_e]: 2,
    [Army_Enum_1.EArmyType.army_f]: 3,
};
// Elkaisar.BattelReplay.DeadSol = {
exports.CDeadSol = {
    [`${Battle_Enum_1.EBattleSide.Defense}.${Army_Enum_1.EArmyType.army_a}`]: 1,
    [`${Battle_Enum_1.EBattleSide.Defense}.${Army_Enum_1.EArmyType.army_b}`]: 6,
    [`${Battle_Enum_1.EBattleSide.Defense}.${Army_Enum_1.EArmyType.army_c}`]: 4,
    [`${Battle_Enum_1.EBattleSide.Defense}.${Army_Enum_1.EArmyType.army_d}`]: 2,
    [`${Battle_Enum_1.EBattleSide.Defense}.${Army_Enum_1.EArmyType.army_e}`]: 8,
    [`${Battle_Enum_1.EBattleSide.Defense}.${Army_Enum_1.EArmyType.army_f}`]: 9,
    [`${Battle_Enum_1.EBattleSide.Attack}.${Army_Enum_1.EArmyType.army_a}`]: 0,
    [`${Battle_Enum_1.EBattleSide.Attack}.${Army_Enum_1.EArmyType.army_b}`]: 7,
    [`${Battle_Enum_1.EBattleSide.Attack}.${Army_Enum_1.EArmyType.army_c}`]: 5,
    [`${Battle_Enum_1.EBattleSide.Attack}.${Army_Enum_1.EArmyType.army_d}`]: 3,
    [`${Battle_Enum_1.EBattleSide.Attack}.${Army_Enum_1.EArmyType.army_e}`]: 9,
    [`${Battle_Enum_1.EBattleSide.Attack}.${Army_Enum_1.EArmyType.army_f}`]: 11,
};
// Elkaisar.BattelReplay.TroopsAnimation = {
exports.CTroopsAnimation = {
    [Army_Enum_1.EArmyType.army_a]: {
        start: 0,
        end: 3,
        Key: "{Key}.A.Side.{Side}.Frame.{FrameIndex}.png",
        Dead: "Dead.A.Side.{Side}.png",
    },
    [Army_Enum_1.EArmyType.army_b]: {
        start: 0,
        end: 3,
        Key: "{Key}.B.Side.{Side}.Frame.{FrameIndex}.png",
        Dead: "Dead.B.Side.{Side}.png",
    },
    [Army_Enum_1.EArmyType.army_c]: {
        start: 0,
        end: 3,
        Key: "{Key}.C.Side.{Side}.Frame.{FrameIndex}.png",
        Dead: "Dead.C.Side.{Side}.png",
    },
    [Army_Enum_1.EArmyType.army_d]: {
        start: 0,
        end: 3,
        Key: "{Key}.D.Side.{Side}.Frame.{FrameIndex}.png",
        Dead: "Dead.D.Side.{Side}.png",
    },
    [Army_Enum_1.EArmyType.army_e]: {
        start: 0,
        end: 3,
        Key: "{Key}.E.Side.{Side}.Frame.{FrameIndex}.png",
        Dead: "Dead.E.Side.{Side}.png",
    },
    [Army_Enum_1.EArmyType.army_f]: {
        start: 0,
        end: 3,
        Key: "{Key}.F.Side.{Side}.Frame.{FrameIndex}.png",
        Dead: "Dead.F.Side.{Side}.png",
    },
};
// Elkaisar.BattelReplay.TroopsFrams = {
//   Idle: {
//     [Elkaisar.BattelReplay.AttackSides.Defence]: [
//       [0, 0],
//       [1, 0],
//       [2, 0],
//       [3, 0],
//     ],
//     [Elkaisar.BattelReplay.AttackSides.Attack]: [
//       [4, 0],
//       [5, 0],
//       [6, 0],
//       [7, 0],
//     ],
//   },
//   Walk: {
//     [Elkaisar.BattelReplay.AttackSides.Defence]: [
//       [0, 1],
//       [1, 1],
//       [2, 1],
//       [3, 1],
//       [4, 1],
//       [5, 1],
//       [6, 1],
//       [7, 1],
//     ],
//     [Elkaisar.BattelReplay.AttackSides.Attack]: [
//       [0, 2],
//       [1, 2],
//       [2, 2],
//       [3, 2],
//       [4, 2],
//       [5, 2],
//       [6, 2],
//       [7, 2],
//     ],
//   },
//   Melee: {
//     [Elkaisar.BattelReplay.AttackSides.Defence]: [
//       [0, 3],
//       [1, 3],
//       [2, 3],
//       [3, 3],
//       [4, 3],
//       [5, 3],
//       [6, 3],
//       [7, 3],
//       [8, 3],
//       [9, 3],
//     ],
//     [Elkaisar.BattelReplay.AttackSides.Attack]: [
//       [0, 4],
//       [1, 4],
//       [2, 4],
//       [3, 4],
//       [4, 4],
//       [5, 4],
//       [6, 4],
//       [7, 4],
//       [8, 4],
//       [9, 4],
//     ],
//   },
// };
// Elkaisar.BattelReplay.Loading = function () {};
// Elkaisar.BattelReplay.Loaded = function () {
//   Crafty.enterScene("BattelGround");
// };
// Elkaisar.BattelReplay.LoadingErr = function () {};
/*Crafty.load(
 Elkaisar.BattelReplay.ResToLoad,
 Elkaisar.BattelReplay.Loaded,
 Elkaisar.BattelReplay.Loading,
 Elkaisar.BattelReplay.LoadingErr
 );*/
// Elkaisar.BattelReplay.drawControllPanel = function () {
//   var Win = $("#BattelReplayCanvas canvas");
//   $("#PlayControlBoard").width(Win.width());
//   $("#PlayControlBoard").css({
//     top: Win.height() - $("#PlayControlBoard").height(),
//     left: Win.offset().left,
//     display: "flex",
//   });
//   $("#BloodBar").css({ display: "flex", left: Win.offset().left });
//   $("#BloodBar").width(Win.width());
//   $("#RightSideData").css({ right: Win.offset().left + 3, display: "block" });
// };


/***/ },

/***/ "./Src/UI/UIBattleReport.ts"
/*!**********************************!*\
  !*** ./Src/UI/UIBattleReport.ts ***!
  \**********************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.UIBattleReport = void 0;
const XTranslate_1 = __webpack_require__(/*! ../../../Client/Base/Translate/XTranslate */ "../Client/Base/Translate/XTranslate.ts");
const Item_Static_1 = __webpack_require__(/*! ../../../Client/Module/Player/Item.Static */ "../Client/Module/Player/Item.Static.ts");
const Battle_Enum_1 = __webpack_require__(/*! ../../../Common/Battle/Battle.Enum */ "../Common/Battle/Battle.Enum.ts");
const BattleTask_Config_1 = __webpack_require__(/*! ../../../Common/Battle/Config/BattleTask.Config */ "../Common/Battle/Config/BattleTask.Config.ts");
const Army_Config_1 = __webpack_require__(/*! ../../../Common/Enum/Army.Config */ "../Common/Enum/Army.Config.ts");
const Army_Enum_1 = __webpack_require__(/*! ../../../Common/Enum/Army.Enum */ "../Common/Enum/Army.Enum.ts");
const Hero_Enum_1 = __webpack_require__(/*! ../../../Common/Hero/Hero.Enum */ "../Common/Hero/Hero.Enum.ts");
const XDate_1 = __webpack_require__(/*! ../../../Common/XDate */ "../Common/XDate.ts");
const BattleReplay_Service_1 = __webpack_require__(/*! ../BattleReplay.Service */ "./Src/BattleReplay.Service.ts");
class UIBattleReport {
    static dialogBox(title, nav_bar, content, page_num) {
        let nav_list = "";
        nav_bar.forEach(function (one, index) {
            nav_list += ` 
        <li head_title="${one.ID}" class="${index === 0 ? "selected" : ""}" >
          ${XTranslate_1.XTranslate.i18n(one["title"])}
        </li>`;
        });
        let lTop = 125;
        if (cc.winSize.height < 800) {
            lTop = 50;
        }
        return `
        <div id='dialg_box' style="top: ${lTop}px;">
            <div class="head_bar">
                <img src="/Res/style/head_bar.png">
                <div class="title">${title} </div>
            </div>
            <div class="nav_bar">
                <div class="left-nav">
                    <ul>${nav_list}</ul>
                </div>
                <div class="right-nav">
                    <div class="nav_icon">
                      <img  class ="close_dialog" src="/Res/btns/close_b.png">
                      ${page_num === undefined
            ? ""
            : `
                        <img id="nav-item-box-right" data-current-offset="1" src="/Res/btns/right.png" class="right">
                        <h1 id="page-nav-holder"> 1/${page_num}</h1>
                        <img id="nav-item-box-left" data-current-offset="1" src="/Res/btns/left.png" class="left">
                      `}
                    </div>
                </div>
            </div>
            ${content}
        </div>`;
    }
    static _GetAttacker() {
        const lIDPlayer = BattleReplay_Service_1.BattleReplayService.BattelReplayData.battle_data.id_player;
        return Object.values(BattleReplay_Service_1.BattleReplayService.BattelReplayData.player_list).find((player) => player.Player.id_player === lIDPlayer);
    }
    static getReportContent() {
        const report = BattleReplay_Service_1.BattleReplayService.BattelReplayData;
        const lAttacker = this._GetAttacker();
        const lBattle = BattleReplay_Service_1.BattleReplayService.BattelReplayData.battle_data;
        const lAllResourcePrizeList = BattleReplay_Service_1.BattleReplayService.getAllResourcePrizeList();
        const lAllItemPrizeList = BattleReplay_Service_1.BattleReplayService.getAllPrizeList();
        let cont = `
      <div class="box_content for_msg for_Br " id="battel-report-msg">
        <div class="left-content full">
            <div id="battel_r_upper">
                <div class="header">
                    <div class="pull-R th" style="direction: rtl;">
                        انشئ الملك ${lAttacker?.Player.name} ${XTranslate_1.XTranslate.i18n(BattleTask_Config_1.CBattleTaskConfig[lBattle.task].title)} الى   [ ${lBattle.x_coord} , ${lBattle.y_coord} ] م ${lBattle.unit_lvl}
                    </div>
                    <div class="pull-L th">
                        ${XDate_1.XDate.dateTimeFormatShort(lBattle.time_start)}
                    </div>
                </div>
                <p style="clear: both"></p>
                <div class="result-icon">
                    ${BattleReplay_Service_1.BattleReplayService._AttackerWin()
            ? "<div class='win'>فوز</div> "
            : "<div class='def'>هزيمة</div>"}
                </div>
                <div class="battel-desc">
                </div>
                <div class="resource-row">
                    <ul>
                        <li><img ondragstart="return false;" src="/Res/style/food.png"><span class="l-number"> ${lAllResourcePrizeList.food ?? 0} </span></li>
                        <li><img ondragstart="return false;" src="/Res/style/wood.png"><span class="l-number"> ${lAllResourcePrizeList.wood ?? 0}</span></li>
                        <li><img ondragstart="return false;" src="/Res/style/stone.png"><span class="l-number">${lAllResourcePrizeList.stone ?? 0} </span></li>
                        <li><img ondragstart="return false;" src="/Res/style/iron.png"><span class="l-number"> ${lAllResourcePrizeList.metal ?? 0} </span></li>
                        <li><img ondragstart="return false;" src="/Res/style/coin.png"><span class="l-number"> ${lAllResourcePrizeList.coin ?? 0} </span></li>
                    </ul>
                </div>
                <div class="prize-row flex">
                    <ul>`;
        for (const [key, lIitem] of Object.entries(lAllItemPrizeList)) {
            cont += `
        <li>
            <img src="/${Item_Static_1.ItemStatic.image(key)}" />
            <div class="amount stroke l-number">${lIitem}</div>
        </li>`;
        }
        cont += `
            </ul>
            <p>جولات: ${report.round_list.length}</p>
        </div>
    </div>`;
        cont += `
      <div id="battel-detail">
        <div class="your_side">
            <ul>`;
        for (const lHero of report.hero_list) {
            if (lHero.Hero.side == Battle_Enum_1.EBattleSide.Attack) {
                let tr_1 = "";
                let tr_2 = "";
                for (const lPos of [
                    Army_Enum_1.EArmyPlaceOnHero.f_1,
                    Army_Enum_1.EArmyPlaceOnHero.f_2,
                    Army_Enum_1.EArmyPlaceOnHero.f_3,
                    Army_Enum_1.EArmyPlaceOnHero.b_1,
                    Army_Enum_1.EArmyPlaceOnHero.b_2,
                    Army_Enum_1.EArmyPlaceOnHero.b_3,
                ]) {
                    let lCell = "";
                    if (lHero.pre[lPos] != 0) {
                        lCell = ` 
              <li>
                <img src="/${Army_Config_1.CArmyConfig[lHero.type[lPos]].Icon}" />
                <div class="pre-amount stroke">${lHero.pre[lPos]}</div>
                <div class="post-amount l-number" title="القتلى">${Math.floor(lHero.dead?.[lPos] ?? Math.max(0, lHero.pre[lPos] - lHero.post[lPos]))}</div>
              </li>`;
                    }
                    else {
                        lCell = "<li></li>";
                    }
                    if (lPos == Army_Enum_1.EArmyPlaceOnHero.f_1 ||
                        lPos == Army_Enum_1.EArmyPlaceOnHero.f_2 ||
                        lPos == Army_Enum_1.EArmyPlaceOnHero.f_3) {
                        tr_1 += lCell;
                    }
                    else {
                        tr_2 += lCell;
                    }
                }
                cont += `
          <li>
            <div class="hero">
              <div class="name">
                  ${lHero?.Hero?.name ? `${BattleReplay_Service_1.BattleReplayService.GetPlayerName(lHero.Hero?.id_player)}(${lHero?.Hero?.name})` : "بطل النظام"}
              </div>
                <div class="image">
                    <img src="/${Hero_Enum_1.CHeroAvatar[lHero.Hero.avatar] ??
                    "Res/icons/hero/eq-bg.png"}" />
                    <div class="xp stroke"></div>
                </div>
                </div>
                <div class="army">
                    <ol>
                    ${tr_1}
                    ${tr_2}
                    </ol>
                </div>
            </li>`;
            }
        }
        cont += `
              </ul>
            </div>
            <div class="enemy_side">
                <ul>`;
        for (const lHero of report.hero_list) {
            if (lHero.Hero.side == Battle_Enum_1.EBattleSide.Defense) {
                let tr_1 = "";
                let tr_2 = "";
                for (const lPos of [
                    Army_Enum_1.EArmyPlaceOnHero.f_1,
                    Army_Enum_1.EArmyPlaceOnHero.f_2,
                    Army_Enum_1.EArmyPlaceOnHero.f_3,
                    Army_Enum_1.EArmyPlaceOnHero.b_1,
                    Army_Enum_1.EArmyPlaceOnHero.b_2,
                    Army_Enum_1.EArmyPlaceOnHero.b_3,
                ]) {
                    if (lHero.pre[lPos] != 0) {
                        let lCell = "";
                        lCell = ` 
              <li>
                  <img src="/${Army_Config_1.CArmyConfig[lHero.type[lPos]]?.Icon}" />
                  <div class="pre-amount stroke">${lHero.pre[lPos]}</div>
                  <div class="post-amount l-number" title="القتلى">${Math.floor(lHero.dead?.[lPos] ?? Math.max(0, lHero.pre[lPos] - lHero.post[lPos]))}</div>
              </li>`;
                        if (lPos == Army_Enum_1.EArmyPlaceOnHero.f_1 ||
                            lPos == Army_Enum_1.EArmyPlaceOnHero.f_2 ||
                            lPos == Army_Enum_1.EArmyPlaceOnHero.f_3) {
                            tr_1 += lCell;
                        }
                        else {
                            tr_2 += lCell;
                        }
                    }
                }
                cont += `
          <li>
            <div class="hero">
                <div class="name">
                  ${lHero?.Hero?.name ? `${BattleReplay_Service_1.BattleReplayService.GetPlayerName(lHero.Hero?.id_player)}(${lHero?.Hero?.name})` : "بطل النظام"}
                </div>
                <div class="image">
                  <img src="/${Hero_Enum_1.CHeroAvatar[lHero.Hero.avatar] || "Res/icons/hero/eq-bg.png"}"/>
              </div>
            </div>
            <div class="army">
                <ol>
                ${tr_1}
                ${tr_2}
                </ol>
            </div>
        </li>`;
            }
        }
        cont += ` 
                  </ul>
              </div>
          </div>
      </div>
      <div class="right-content-footer" rank_for="players">
          <div class="buttons">
              <ul>
              </ul>
          </div>
      </div>
    </div>`;
        return cont;
    }
}
exports.UIBattleReport = UIBattleReport;


/***/ },

/***/ "./Src/UI/UIBoardControl.ts"
/*!**********************************!*\
  !*** ./Src/UI/UIBoardControl.ts ***!
  \**********************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.UIBoardControl = void 0;
const XLoader_1 = __webpack_require__(/*! ../../../Client/Core/Engine/XLoader */ "../Client/Core/Engine/XLoader.ts");
const Base_1 = __webpack_require__(/*! ../BR/Base */ "./Src/BR/Base.ts");
class UIBoardControl extends ccui.Widget {
    constructor() {
        super(...arguments);
        this._PlayerState = "Play";
    }
    static async Create() {
        const lPanel = new UIBoardControl();
        lPanel.setContentSize(cc.size(1000, 150));
        await lPanel._Init();
        lPanel.setScale(1, 1);
        return lPanel;
    }
    async _Init() {
        await this._InitBG();
        this._PlayBtn = (0, Base_1.GetDeepChild)(this, "BtnPlay");
        this._NextBtn = (0, Base_1.GetDeepChild)(this, "BtnNext");
        this._PrevBtn = (0, Base_1.GetDeepChild)(this, "BtnBack");
        this._PlayBtn?.addTouchEventListener((sender, type) => {
            if (type !== ccui.Widget.TOUCH_ENDED)
                return;
            if (this._PlayerState === "Play") {
                this._PlayerState = "Pause";
                this._OnPauseClick?.(this._PlayBtn, ccui.Widget.TOUCH_ENDED);
                this._PlayBtn.loadTextureNormal("/BRAsset/Img/BattleReplay/BattleControl/button_start-Normal.png", ccui.Widget.LOCAL_TEXTURE);
                this._PlayBtn.loadTexturePressed("/BRAsset/Img/BattleReplay/BattleControl/button_start-ClickDown.png", ccui.Widget.LOCAL_TEXTURE);
            }
            else {
                this._PlayerState = "Play";
                this._OnPlayClick?.(this._PlayBtn, ccui.Widget.TOUCH_ENDED);
                this._PlayBtn.loadTextureNormal("/BRAsset/Img/BattleReplay/BattleControl/button_stop-Normal.png", ccui.Widget.LOCAL_TEXTURE);
                this._PlayBtn.loadTexturePressed("/BRAsset/Img/BattleReplay/BattleControl/button_stop-ClickDown.png", ccui.Widget.LOCAL_TEXTURE);
            }
        });
        this._NextBtn?.addTouchEventListener((sender, type) => {
            if (type !== ccui.Widget.TOUCH_ENDED)
                return;
            this._OnNextClick?.(this._NextBtn, ccui.Widget.TOUCH_ENDED);
        }, this);
        this._PrevBtn?.addTouchEventListener((sender, type) => {
            if (type !== ccui.Widget.TOUCH_ENDED)
                return;
            this._OnPrevClick?.(this._PrevBtn, ccui.Widget.TOUCH_ENDED);
        }, this);
    }
    async _InitBG() {
        const lUi = await XLoader_1.XLoader.Inst.LoadCCSNode("/BRAsset/Assets/UI/ControlBoard.json");
        this._Bg = lUi.node;
        this._Bg.setScale(1, 1);
        this._Bg.setPosition(0, 0);
        this._Bg.setAnchorPoint(0, 0);
        this.addChild(this._Bg);
        this._RoundLabel = new ccui.Text();
        this._RoundLabel.setFontSize(12);
        this._RoundLabel.setColor(cc.color(255, 255, 255));
        this._RoundLabel.setPosition(500, 100);
        this._RoundLabel.setAnchorPoint(0.5, 0.5);
        this._Bg.addChild(this._RoundLabel);
        this._RoundLabel.setString("Round 1");
        // this._RoundLabel.enableOutline(cc.color(0, 0, 0), cc.size(2, 2));
    }
    SetCurrentRound(round) {
        const lRoundText = `Round ${round}`;
        this._RoundLabel.setString(lRoundText);
    }
}
exports.UIBoardControl = UIBoardControl;


/***/ },

/***/ "./Src/UI/UICityDef.ts"
/*!*****************************!*\
  !*** ./Src/UI/UICityDef.ts ***!
  \*****************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.UICityDef = void 0;
const XLoader_1 = __webpack_require__(/*! ../../../Client/Core/Engine/XLoader */ "../Client/Core/Engine/XLoader.ts");
const Base_1 = __webpack_require__(/*! ../BR/Base */ "./Src/BR/Base.ts");
const UIBattleReport_1 = __webpack_require__(/*! ./UIBattleReport */ "./Src/UI/UIBattleReport.ts");
class UICityDef extends ccui.Widget {
    static Create() {
        const lPanel = new UICityDef();
        lPanel.setContentSize(cc.size(130, 150));
        lPanel._Init();
        return lPanel;
    }
    async _Init() {
        await this._InitBG();
        this._ShowResultBtn = (0, Base_1.GetDeepChild)(this._Bg, "ShowResult");
        this._ShowResultBtn?.addTouchEventListener(this.ShowReport.bind(this));
    }
    ShowReport(btn, type) {
        if (type !== ccui.Widget.TOUCH_ENDED)
            return;
        cc.log("ShowReport clicked");
        if (document.getElementById("dialg_box")) {
            return;
        }
        const lDialogBox = UIBattleReport_1.UIBattleReport.dialogBox("Battle Report", [], UIBattleReport_1.UIBattleReport.getReportContent(), undefined);
        document.getElementById("battle-report-container").innerHTML =
            lDialogBox ?? null;
        document
            .getElementsByClassName("close_dialog")[0]
            .addEventListener("click", () => {
            const container = document.getElementById("battle-report-container");
            if (container) {
                container.innerHTML = "";
            }
        });
    }
    async _InitBG() {
        const lUi = await XLoader_1.XLoader.Inst.LoadCCSNode("/BRAsset/Assets/UI/CityDefence.json");
        this._Bg = lUi.node;
        this._Bg.setPosition(0, 0);
        this._Bg.setAnchorPoint(0, 0);
        this.addChild(this._Bg);
    }
}
exports.UICityDef = UICityDef;


/***/ },

/***/ "./Src/UI/UIRoundTitle.ts"
/*!********************************!*\
  !*** ./Src/UI/UIRoundTitle.ts ***!
  \********************************/
(__unused_webpack_module, exports) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.UIRoundTitle = void 0;
class UIRoundTitle extends ccui.Widget {
    static Create(RoundIndex) {
        const lRoundTitle = new UIRoundTitle();
        lRoundTitle._RoundIndex = RoundIndex;
        lRoundTitle.setContentSize(515, 36);
        lRoundTitle.setAnchorPoint(0.5, 0.5);
        lRoundTitle._Init();
        return lRoundTitle;
    }
    _Init() {
        const lBg = new ccui.ImageView("/BRAsset/Img/BattleReplay/textBg.png");
        lBg.setPosition(-257.5, 0);
        lBg.setContentSize(515, 36);
        lBg.setAnchorPoint(0.5, 0.5);
        this.addChild(lBg);
        const lRoundText = new ccui.Text();
        lRoundText.setFontSize(18);
        lRoundText.setString(`Round ${this._RoundIndex}`);
        lRoundText.setFontName("Arial");
        lRoundText.setColor(cc.color(255, 255, 255));
        lRoundText.setPosition(107.5, 5);
        lRoundText.setContentSize(515, 36);
        lRoundText.setTextHorizontalAlignment(cc.TEXT_ALIGNMENT_CENTER);
        lRoundText.setTextVerticalAlignment(cc.TEXT_ALIGNMENT_CENTER);
        lRoundText.enableOutline(cc.color(0, 0, 0), 2);
        lRoundText.setAnchorPoint(0.5, 0);
        this._Txt = lRoundText;
        this._Bg = lBg;
        lBg.addChild(lRoundText);
    }
    AnimateIn() {
        return new Promise((resolve) => {
            const lMoveBgIn = cc.moveBy(1.25, 500, 0).easing(cc.easeBackOut());
            const lMoveTxtIn = cc.moveBy(1.25, 150, 0).easing(cc.easeBackOut());
            const lDelay = cc.delayTime(2);
            const lMoveBgOut = cc.moveBy(1.25, 500, 0).easing(cc.easeBackIn());
            const lMoveTxtOut = cc.moveBy(1.25, 150, 0).easing(cc.easeBackIn());
            this._Txt.runAction(cc.sequence(lMoveTxtIn, lDelay, lMoveTxtOut));
            this._Bg.runAction(cc.sequence(lMoveBgIn, cc.callFunc(() => {
                resolve();
            }), lDelay, lMoveBgOut, cc.callFunc(() => {
                this._Bg.removeFromParent();
            })));
        });
    }
}
exports.UIRoundTitle = UIRoundTitle;


/***/ },

/***/ "./Src/UI/UITopBar.ts"
/*!****************************!*\
  !*** ./Src/UI/UITopBar.ts ***!
  \****************************/
(__unused_webpack_module, exports, __webpack_require__) {

"use strict";

Object.defineProperty(exports, "__esModule", ({ value: true }));
exports.UITopBar = void 0;
const XLoader_1 = __webpack_require__(/*! ../../../Client/Core/Engine/XLoader */ "../Client/Core/Engine/XLoader.ts");
class UITopBar extends ccui.Widget {
    static Create() {
        const lPanel = new UITopBar();
        lPanel.setContentSize(cc.size(1000, 30));
        lPanel._Init();
        return lPanel;
    }
    async _Init() {
        await this._InitBG();
    }
    async _InitBG() {
        const lUi = await XLoader_1.XLoader.Inst.LoadCCSNode("/BRAsset/Assets/UI/UITopBar.json");
        this._Bg = lUi.node;
        this._Bg.setPosition(0, 0);
        this._Bg.setAnchorPoint(0, 0);
        this.addChild(this._Bg);
    }
}
exports.UITopBar = UITopBar;


/***/ },

/***/ "./node_modules/jquery/dist/jquery.js"
/*!********************************************!*\
  !*** ./node_modules/jquery/dist/jquery.js ***!
  \********************************************/
(module, exports) {

var __WEBPACK_AMD_DEFINE_ARRAY__, __WEBPACK_AMD_DEFINE_RESULT__;/*!
 * jQuery JavaScript Library v3.7.1
 * https://jquery.com/
 *
 * Copyright OpenJS Foundation and other contributors
 * Released under the MIT license
 * https://jquery.org/license
 *
 * Date: 2023-08-28T13:37Z
 */
( function( global, factory ) {

	"use strict";

	if (  true && typeof module.exports === "object" ) {

		// For CommonJS and CommonJS-like environments where a proper `window`
		// is present, execute the factory and get jQuery.
		// For environments that do not have a `window` with a `document`
		// (such as Node.js), expose a factory as module.exports.
		// This accentuates the need for the creation of a real `window`.
		// e.g. var jQuery = require("jquery")(window);
		// See ticket trac-14549 for more info.
		module.exports = global.document ?
			factory( global, true ) :
			function( w ) {
				if ( !w.document ) {
					throw new Error( "jQuery requires a window with a document" );
				}
				return factory( w );
			};
	} else {
		factory( global );
	}

// Pass this if window is not defined yet
} )( typeof window !== "undefined" ? window : this, function( window, noGlobal ) {

// Edge <= 12 - 13+, Firefox <=18 - 45+, IE 10 - 11, Safari 5.1 - 9+, iOS 6 - 9.1
// throw exceptions when non-strict code (e.g., ASP.NET 4.5) accesses strict mode
// arguments.callee.caller (trac-13335). But as of jQuery 3.0 (2016), strict mode should be common
// enough that all such attempts are guarded in a try block.
"use strict";

var arr = [];

var getProto = Object.getPrototypeOf;

var slice = arr.slice;

var flat = arr.flat ? function( array ) {
	return arr.flat.call( array );
} : function( array ) {
	return arr.concat.apply( [], array );
};


var push = arr.push;

var indexOf = arr.indexOf;

var class2type = {};

var toString = class2type.toString;

var hasOwn = class2type.hasOwnProperty;

var fnToString = hasOwn.toString;

var ObjectFunctionString = fnToString.call( Object );

var support = {};

var isFunction = function isFunction( obj ) {

		// Support: Chrome <=57, Firefox <=52
		// In some browsers, typeof returns "function" for HTML <object> elements
		// (i.e., `typeof document.createElement( "object" ) === "function"`).
		// We don't want to classify *any* DOM node as a function.
		// Support: QtWeb <=3.8.5, WebKit <=534.34, wkhtmltopdf tool <=0.12.5
		// Plus for old WebKit, typeof returns "function" for HTML collections
		// (e.g., `typeof document.getElementsByTagName("div") === "function"`). (gh-4756)
		return typeof obj === "function" && typeof obj.nodeType !== "number" &&
			typeof obj.item !== "function";
	};


var isWindow = function isWindow( obj ) {
		return obj != null && obj === obj.window;
	};


var document = window.document;



	var preservedScriptAttributes = {
		type: true,
		src: true,
		nonce: true,
		noModule: true
	};

	function DOMEval( code, node, doc ) {
		doc = doc || document;

		var i, val,
			script = doc.createElement( "script" );

		script.text = code;
		if ( node ) {
			for ( i in preservedScriptAttributes ) {

				// Support: Firefox 64+, Edge 18+
				// Some browsers don't support the "nonce" property on scripts.
				// On the other hand, just using `getAttribute` is not enough as
				// the `nonce` attribute is reset to an empty string whenever it
				// becomes browsing-context connected.
				// See https://github.com/whatwg/html/issues/2369
				// See https://html.spec.whatwg.org/#nonce-attributes
				// The `node.getAttribute` check was added for the sake of
				// `jQuery.globalEval` so that it can fake a nonce-containing node
				// via an object.
				val = node[ i ] || node.getAttribute && node.getAttribute( i );
				if ( val ) {
					script.setAttribute( i, val );
				}
			}
		}
		doc.head.appendChild( script ).parentNode.removeChild( script );
	}


function toType( obj ) {
	if ( obj == null ) {
		return obj + "";
	}

	// Support: Android <=2.3 only (functionish RegExp)
	return typeof obj === "object" || typeof obj === "function" ?
		class2type[ toString.call( obj ) ] || "object" :
		typeof obj;
}
/* global Symbol */
// Defining this global in .eslintrc.json would create a danger of using the global
// unguarded in another place, it seems safer to define global only for this module



var version = "3.7.1",

	rhtmlSuffix = /HTML$/i,

	// Define a local copy of jQuery
	jQuery = function( selector, context ) {

		// The jQuery object is actually just the init constructor 'enhanced'
		// Need init if jQuery is called (just allow error to be thrown if not included)
		return new jQuery.fn.init( selector, context );
	};

jQuery.fn = jQuery.prototype = {

	// The current version of jQuery being used
	jquery: version,

	constructor: jQuery,

	// The default length of a jQuery object is 0
	length: 0,

	toArray: function() {
		return slice.call( this );
	},

	// Get the Nth element in the matched element set OR
	// Get the whole matched element set as a clean array
	get: function( num ) {

		// Return all the elements in a clean array
		if ( num == null ) {
			return slice.call( this );
		}

		// Return just the one element from the set
		return num < 0 ? this[ num + this.length ] : this[ num ];
	},

	// Take an array of elements and push it onto the stack
	// (returning the new matched element set)
	pushStack: function( elems ) {

		// Build a new jQuery matched element set
		var ret = jQuery.merge( this.constructor(), elems );

		// Add the old object onto the stack (as a reference)
		ret.prevObject = this;

		// Return the newly-formed element set
		return ret;
	},

	// Execute a callback for every element in the matched set.
	each: function( callback ) {
		return jQuery.each( this, callback );
	},

	map: function( callback ) {
		return this.pushStack( jQuery.map( this, function( elem, i ) {
			return callback.call( elem, i, elem );
		} ) );
	},

	slice: function() {
		return this.pushStack( slice.apply( this, arguments ) );
	},

	first: function() {
		return this.eq( 0 );
	},

	last: function() {
		return this.eq( -1 );
	},

	even: function() {
		return this.pushStack( jQuery.grep( this, function( _elem, i ) {
			return ( i + 1 ) % 2;
		} ) );
	},

	odd: function() {
		return this.pushStack( jQuery.grep( this, function( _elem, i ) {
			return i % 2;
		} ) );
	},

	eq: function( i ) {
		var len = this.length,
			j = +i + ( i < 0 ? len : 0 );
		return this.pushStack( j >= 0 && j < len ? [ this[ j ] ] : [] );
	},

	end: function() {
		return this.prevObject || this.constructor();
	},

	// For internal use only.
	// Behaves like an Array's method, not like a jQuery method.
	push: push,
	sort: arr.sort,
	splice: arr.splice
};

jQuery.extend = jQuery.fn.extend = function() {
	var options, name, src, copy, copyIsArray, clone,
		target = arguments[ 0 ] || {},
		i = 1,
		length = arguments.length,
		deep = false;

	// Handle a deep copy situation
	if ( typeof target === "boolean" ) {
		deep = target;

		// Skip the boolean and the target
		target = arguments[ i ] || {};
		i++;
	}

	// Handle case when target is a string or something (possible in deep copy)
	if ( typeof target !== "object" && !isFunction( target ) ) {
		target = {};
	}

	// Extend jQuery itself if only one argument is passed
	if ( i === length ) {
		target = this;
		i--;
	}

	for ( ; i < length; i++ ) {

		// Only deal with non-null/undefined values
		if ( ( options = arguments[ i ] ) != null ) {

			// Extend the base object
			for ( name in options ) {
				copy = options[ name ];

				// Prevent Object.prototype pollution
				// Prevent never-ending loop
				if ( name === "__proto__" || target === copy ) {
					continue;
				}

				// Recurse if we're merging plain objects or arrays
				if ( deep && copy && ( jQuery.isPlainObject( copy ) ||
					( copyIsArray = Array.isArray( copy ) ) ) ) {
					src = target[ name ];

					// Ensure proper type for the source value
					if ( copyIsArray && !Array.isArray( src ) ) {
						clone = [];
					} else if ( !copyIsArray && !jQuery.isPlainObject( src ) ) {
						clone = {};
					} else {
						clone = src;
					}
					copyIsArray = false;

					// Never move original objects, clone them
					target[ name ] = jQuery.extend( deep, clone, copy );

				// Don't bring in undefined values
				} else if ( copy !== undefined ) {
					target[ name ] = copy;
				}
			}
		}
	}

	// Return the modified object
	return target;
};

jQuery.extend( {

	// Unique for each copy of jQuery on the page
	expando: "jQuery" + ( version + Math.random() ).replace( /\D/g, "" ),

	// Assume jQuery is ready without the ready module
	isReady: true,

	error: function( msg ) {
		throw new Error( msg );
	},

	noop: function() {},

	isPlainObject: function( obj ) {
		var proto, Ctor;

		// Detect obvious negatives
		// Use toString instead of jQuery.type to catch host objects
		if ( !obj || toString.call( obj ) !== "[object Object]" ) {
			return false;
		}

		proto = getProto( obj );

		// Objects with no prototype (e.g., `Object.create( null )`) are plain
		if ( !proto ) {
			return true;
		}

		// Objects with prototype are plain iff they were constructed by a global Object function
		Ctor = hasOwn.call( proto, "constructor" ) && proto.constructor;
		return typeof Ctor === "function" && fnToString.call( Ctor ) === ObjectFunctionString;
	},

	isEmptyObject: function( obj ) {
		var name;

		for ( name in obj ) {
			return false;
		}
		return true;
	},

	// Evaluates a script in a provided context; falls back to the global one
	// if not specified.
	globalEval: function( code, options, doc ) {
		DOMEval( code, { nonce: options && options.nonce }, doc );
	},

	each: function( obj, callback ) {
		var length, i = 0;

		if ( isArrayLike( obj ) ) {
			length = obj.length;
			for ( ; i < length; i++ ) {
				if ( callback.call( obj[ i ], i, obj[ i ] ) === false ) {
					break;
				}
			}
		} else {
			for ( i in obj ) {
				if ( callback.call( obj[ i ], i, obj[ i ] ) === false ) {
					break;
				}
			}
		}

		return obj;
	},


	// Retrieve the text value of an array of DOM nodes
	text: function( elem ) {
		var node,
			ret = "",
			i = 0,
			nodeType = elem.nodeType;

		if ( !nodeType ) {

			// If no nodeType, this is expected to be an array
			while ( ( node = elem[ i++ ] ) ) {

				// Do not traverse comment nodes
				ret += jQuery.text( node );
			}
		}
		if ( nodeType === 1 || nodeType === 11 ) {
			return elem.textContent;
		}
		if ( nodeType === 9 ) {
			return elem.documentElement.textContent;
		}
		if ( nodeType === 3 || nodeType === 4 ) {
			return elem.nodeValue;
		}

		// Do not include comment or processing instruction nodes

		return ret;
	},

	// results is for internal usage only
	makeArray: function( arr, results ) {
		var ret = results || [];

		if ( arr != null ) {
			if ( isArrayLike( Object( arr ) ) ) {
				jQuery.merge( ret,
					typeof arr === "string" ?
						[ arr ] : arr
				);
			} else {
				push.call( ret, arr );
			}
		}

		return ret;
	},

	inArray: function( elem, arr, i ) {
		return arr == null ? -1 : indexOf.call( arr, elem, i );
	},

	isXMLDoc: function( elem ) {
		var namespace = elem && elem.namespaceURI,
			docElem = elem && ( elem.ownerDocument || elem ).documentElement;

		// Assume HTML when documentElement doesn't yet exist, such as inside
		// document fragments.
		return !rhtmlSuffix.test( namespace || docElem && docElem.nodeName || "HTML" );
	},

	// Support: Android <=4.0 only, PhantomJS 1 only
	// push.apply(_, arraylike) throws on ancient WebKit
	merge: function( first, second ) {
		var len = +second.length,
			j = 0,
			i = first.length;

		for ( ; j < len; j++ ) {
			first[ i++ ] = second[ j ];
		}

		first.length = i;

		return first;
	},

	grep: function( elems, callback, invert ) {
		var callbackInverse,
			matches = [],
			i = 0,
			length = elems.length,
			callbackExpect = !invert;

		// Go through the array, only saving the items
		// that pass the validator function
		for ( ; i < length; i++ ) {
			callbackInverse = !callback( elems[ i ], i );
			if ( callbackInverse !== callbackExpect ) {
				matches.push( elems[ i ] );
			}
		}

		return matches;
	},

	// arg is for internal usage only
	map: function( elems, callback, arg ) {
		var length, value,
			i = 0,
			ret = [];

		// Go through the array, translating each of the items to their new values
		if ( isArrayLike( elems ) ) {
			length = elems.length;
			for ( ; i < length; i++ ) {
				value = callback( elems[ i ], i, arg );

				if ( value != null ) {
					ret.push( value );
				}
			}

		// Go through every key on the object,
		} else {
			for ( i in elems ) {
				value = callback( elems[ i ], i, arg );

				if ( value != null ) {
					ret.push( value );
				}
			}
		}

		// Flatten any nested arrays
		return flat( ret );
	},

	// A global GUID counter for objects
	guid: 1,

	// jQuery.support is not used in Core but other projects attach their
	// properties to it so it needs to exist.
	support: support
} );

if ( typeof Symbol === "function" ) {
	jQuery.fn[ Symbol.iterator ] = arr[ Symbol.iterator ];
}

// Populate the class2type map
jQuery.each( "Boolean Number String Function Array Date RegExp Object Error Symbol".split( " " ),
	function( _i, name ) {
		class2type[ "[object " + name + "]" ] = name.toLowerCase();
	} );

function isArrayLike( obj ) {

	// Support: real iOS 8.2 only (not reproducible in simulator)
	// `in` check used to prevent JIT error (gh-2145)
	// hasOwn isn't used here due to false negatives
	// regarding Nodelist length in IE
	var length = !!obj && "length" in obj && obj.length,
		type = toType( obj );

	if ( isFunction( obj ) || isWindow( obj ) ) {
		return false;
	}

	return type === "array" || length === 0 ||
		typeof length === "number" && length > 0 && ( length - 1 ) in obj;
}


function nodeName( elem, name ) {

	return elem.nodeName && elem.nodeName.toLowerCase() === name.toLowerCase();

}
var pop = arr.pop;


var sort = arr.sort;


var splice = arr.splice;


var whitespace = "[\\x20\\t\\r\\n\\f]";


var rtrimCSS = new RegExp(
	"^" + whitespace + "+|((?:^|[^\\\\])(?:\\\\.)*)" + whitespace + "+$",
	"g"
);




// Note: an element does not contain itself
jQuery.contains = function( a, b ) {
	var bup = b && b.parentNode;

	return a === bup || !!( bup && bup.nodeType === 1 && (

		// Support: IE 9 - 11+
		// IE doesn't have `contains` on SVG.
		a.contains ?
			a.contains( bup ) :
			a.compareDocumentPosition && a.compareDocumentPosition( bup ) & 16
	) );
};




// CSS string/identifier serialization
// https://drafts.csswg.org/cssom/#common-serializing-idioms
var rcssescape = /([\0-\x1f\x7f]|^-?\d)|^-$|[^\x80-\uFFFF\w-]/g;

function fcssescape( ch, asCodePoint ) {
	if ( asCodePoint ) {

		// U+0000 NULL becomes U+FFFD REPLACEMENT CHARACTER
		if ( ch === "\0" ) {
			return "\uFFFD";
		}

		// Control characters and (dependent upon position) numbers get escaped as code points
		return ch.slice( 0, -1 ) + "\\" + ch.charCodeAt( ch.length - 1 ).toString( 16 ) + " ";
	}

	// Other potentially-special ASCII characters get backslash-escaped
	return "\\" + ch;
}

jQuery.escapeSelector = function( sel ) {
	return ( sel + "" ).replace( rcssescape, fcssescape );
};




var preferredDoc = document,
	pushNative = push;

( function() {

var i,
	Expr,
	outermostContext,
	sortInput,
	hasDuplicate,
	push = pushNative,

	// Local document vars
	document,
	documentElement,
	documentIsHTML,
	rbuggyQSA,
	matches,

	// Instance-specific data
	expando = jQuery.expando,
	dirruns = 0,
	done = 0,
	classCache = createCache(),
	tokenCache = createCache(),
	compilerCache = createCache(),
	nonnativeSelectorCache = createCache(),
	sortOrder = function( a, b ) {
		if ( a === b ) {
			hasDuplicate = true;
		}
		return 0;
	},

	booleans = "checked|selected|async|autofocus|autoplay|controls|defer|disabled|hidden|ismap|" +
		"loop|multiple|open|readonly|required|scoped",

	// Regular expressions

	// https://www.w3.org/TR/css-syntax-3/#ident-token-diagram
	identifier = "(?:\\\\[\\da-fA-F]{1,6}" + whitespace +
		"?|\\\\[^\\r\\n\\f]|[\\w-]|[^\0-\\x7f])+",

	// Attribute selectors: https://www.w3.org/TR/selectors/#attribute-selectors
	attributes = "\\[" + whitespace + "*(" + identifier + ")(?:" + whitespace +

		// Operator (capture 2)
		"*([*^$|!~]?=)" + whitespace +

		// "Attribute values must be CSS identifiers [capture 5] or strings [capture 3 or capture 4]"
		"*(?:'((?:\\\\.|[^\\\\'])*)'|\"((?:\\\\.|[^\\\\\"])*)\"|(" + identifier + "))|)" +
		whitespace + "*\\]",

	pseudos = ":(" + identifier + ")(?:\\((" +

		// To reduce the number of selectors needing tokenize in the preFilter, prefer arguments:
		// 1. quoted (capture 3; capture 4 or capture 5)
		"('((?:\\\\.|[^\\\\'])*)'|\"((?:\\\\.|[^\\\\\"])*)\")|" +

		// 2. simple (capture 6)
		"((?:\\\\.|[^\\\\()[\\]]|" + attributes + ")*)|" +

		// 3. anything else (capture 2)
		".*" +
		")\\)|)",

	// Leading and non-escaped trailing whitespace, capturing some non-whitespace characters preceding the latter
	rwhitespace = new RegExp( whitespace + "+", "g" ),

	rcomma = new RegExp( "^" + whitespace + "*," + whitespace + "*" ),
	rleadingCombinator = new RegExp( "^" + whitespace + "*([>+~]|" + whitespace + ")" +
		whitespace + "*" ),
	rdescend = new RegExp( whitespace + "|>" ),

	rpseudo = new RegExp( pseudos ),
	ridentifier = new RegExp( "^" + identifier + "$" ),

	matchExpr = {
		ID: new RegExp( "^#(" + identifier + ")" ),
		CLASS: new RegExp( "^\\.(" + identifier + ")" ),
		TAG: new RegExp( "^(" + identifier + "|[*])" ),
		ATTR: new RegExp( "^" + attributes ),
		PSEUDO: new RegExp( "^" + pseudos ),
		CHILD: new RegExp(
			"^:(only|first|last|nth|nth-last)-(child|of-type)(?:\\(" +
				whitespace + "*(even|odd|(([+-]|)(\\d*)n|)" + whitespace + "*(?:([+-]|)" +
				whitespace + "*(\\d+)|))" + whitespace + "*\\)|)", "i" ),
		bool: new RegExp( "^(?:" + booleans + ")$", "i" ),

		// For use in libraries implementing .is()
		// We use this for POS matching in `select`
		needsContext: new RegExp( "^" + whitespace +
			"*[>+~]|:(even|odd|eq|gt|lt|nth|first|last)(?:\\(" + whitespace +
			"*((?:-\\d)?\\d*)" + whitespace + "*\\)|)(?=[^-]|$)", "i" )
	},

	rinputs = /^(?:input|select|textarea|button)$/i,
	rheader = /^h\d$/i,

	// Easily-parseable/retrievable ID or TAG or CLASS selectors
	rquickExpr = /^(?:#([\w-]+)|(\w+)|\.([\w-]+))$/,

	rsibling = /[+~]/,

	// CSS escapes
	// https://www.w3.org/TR/CSS21/syndata.html#escaped-characters
	runescape = new RegExp( "\\\\[\\da-fA-F]{1,6}" + whitespace +
		"?|\\\\([^\\r\\n\\f])", "g" ),
	funescape = function( escape, nonHex ) {
		var high = "0x" + escape.slice( 1 ) - 0x10000;

		if ( nonHex ) {

			// Strip the backslash prefix from a non-hex escape sequence
			return nonHex;
		}

		// Replace a hexadecimal escape sequence with the encoded Unicode code point
		// Support: IE <=11+
		// For values outside the Basic Multilingual Plane (BMP), manually construct a
		// surrogate pair
		return high < 0 ?
			String.fromCharCode( high + 0x10000 ) :
			String.fromCharCode( high >> 10 | 0xD800, high & 0x3FF | 0xDC00 );
	},

	// Used for iframes; see `setDocument`.
	// Support: IE 9 - 11+, Edge 12 - 18+
	// Removing the function wrapper causes a "Permission Denied"
	// error in IE/Edge.
	unloadHandler = function() {
		setDocument();
	},

	inDisabledFieldset = addCombinator(
		function( elem ) {
			return elem.disabled === true && nodeName( elem, "fieldset" );
		},
		{ dir: "parentNode", next: "legend" }
	);

// Support: IE <=9 only
// Accessing document.activeElement can throw unexpectedly
// https://bugs.jquery.com/ticket/13393
function safeActiveElement() {
	try {
		return document.activeElement;
	} catch ( err ) { }
}

// Optimize for push.apply( _, NodeList )
try {
	push.apply(
		( arr = slice.call( preferredDoc.childNodes ) ),
		preferredDoc.childNodes
	);

	// Support: Android <=4.0
	// Detect silently failing push.apply
	// eslint-disable-next-line no-unused-expressions
	arr[ preferredDoc.childNodes.length ].nodeType;
} catch ( e ) {
	push = {
		apply: function( target, els ) {
			pushNative.apply( target, slice.call( els ) );
		},
		call: function( target ) {
			pushNative.apply( target, slice.call( arguments, 1 ) );
		}
	};
}

function find( selector, context, results, seed ) {
	var m, i, elem, nid, match, groups, newSelector,
		newContext = context && context.ownerDocument,

		// nodeType defaults to 9, since context defaults to document
		nodeType = context ? context.nodeType : 9;

	results = results || [];

	// Return early from calls with invalid selector or context
	if ( typeof selector !== "string" || !selector ||
		nodeType !== 1 && nodeType !== 9 && nodeType !== 11 ) {

		return results;
	}

	// Try to shortcut find operations (as opposed to filters) in HTML documents
	if ( !seed ) {
		setDocument( context );
		context = context || document;

		if ( documentIsHTML ) {

			// If the selector is sufficiently simple, try using a "get*By*" DOM method
			// (excepting DocumentFragment context, where the methods don't exist)
			if ( nodeType !== 11 && ( match = rquickExpr.exec( selector ) ) ) {

				// ID selector
				if ( ( m = match[ 1 ] ) ) {

					// Document context
					if ( nodeType === 9 ) {
						if ( ( elem = context.getElementById( m ) ) ) {

							// Support: IE 9 only
							// getElementById can match elements by name instead of ID
							if ( elem.id === m ) {
								push.call( results, elem );
								return results;
							}
						} else {
							return results;
						}

					// Element context
					} else {

						// Support: IE 9 only
						// getElementById can match elements by name instead of ID
						if ( newContext && ( elem = newContext.getElementById( m ) ) &&
							find.contains( context, elem ) &&
							elem.id === m ) {

							push.call( results, elem );
							return results;
						}
					}

				// Type selector
				} else if ( match[ 2 ] ) {
					push.apply( results, context.getElementsByTagName( selector ) );
					return results;

				// Class selector
				} else if ( ( m = match[ 3 ] ) && context.getElementsByClassName ) {
					push.apply( results, context.getElementsByClassName( m ) );
					return results;
				}
			}

			// Take advantage of querySelectorAll
			if ( !nonnativeSelectorCache[ selector + " " ] &&
				( !rbuggyQSA || !rbuggyQSA.test( selector ) ) ) {

				newSelector = selector;
				newContext = context;

				// qSA considers elements outside a scoping root when evaluating child or
				// descendant combinators, which is not what we want.
				// In such cases, we work around the behavior by prefixing every selector in the
				// list with an ID selector referencing the scope context.
				// The technique has to be used as well when a leading combinator is used
				// as such selectors are not recognized by querySelectorAll.
				// Thanks to Andrew Dupont for this technique.
				if ( nodeType === 1 &&
					( rdescend.test( selector ) || rleadingCombinator.test( selector ) ) ) {

					// Expand context for sibling selectors
					newContext = rsibling.test( selector ) && testContext( context.parentNode ) ||
						context;

					// We can use :scope instead of the ID hack if the browser
					// supports it & if we're not changing the context.
					// Support: IE 11+, Edge 17 - 18+
					// IE/Edge sometimes throw a "Permission denied" error when
					// strict-comparing two documents; shallow comparisons work.
					// eslint-disable-next-line eqeqeq
					if ( newContext != context || !support.scope ) {

						// Capture the context ID, setting it first if necessary
						if ( ( nid = context.getAttribute( "id" ) ) ) {
							nid = jQuery.escapeSelector( nid );
						} else {
							context.setAttribute( "id", ( nid = expando ) );
						}
					}

					// Prefix every selector in the list
					groups = tokenize( selector );
					i = groups.length;
					while ( i-- ) {
						groups[ i ] = ( nid ? "#" + nid : ":scope" ) + " " +
							toSelector( groups[ i ] );
					}
					newSelector = groups.join( "," );
				}

				try {
					push.apply( results,
						newContext.querySelectorAll( newSelector )
					);
					return results;
				} catch ( qsaError ) {
					nonnativeSelectorCache( selector, true );
				} finally {
					if ( nid === expando ) {
						context.removeAttribute( "id" );
					}
				}
			}
		}
	}

	// All others
	return select( selector.replace( rtrimCSS, "$1" ), context, results, seed );
}

/**
 * Create key-value caches of limited size
 * @returns {function(string, object)} Returns the Object data after storing it on itself with
 *	property name the (space-suffixed) string and (if the cache is larger than Expr.cacheLength)
 *	deleting the oldest entry
 */
function createCache() {
	var keys = [];

	function cache( key, value ) {

		// Use (key + " ") to avoid collision with native prototype properties
		// (see https://github.com/jquery/sizzle/issues/157)
		if ( keys.push( key + " " ) > Expr.cacheLength ) {

			// Only keep the most recent entries
			delete cache[ keys.shift() ];
		}
		return ( cache[ key + " " ] = value );
	}
	return cache;
}

/**
 * Mark a function for special use by jQuery selector module
 * @param {Function} fn The function to mark
 */
function markFunction( fn ) {
	fn[ expando ] = true;
	return fn;
}

/**
 * Support testing using an element
 * @param {Function} fn Passed the created element and returns a boolean result
 */
function assert( fn ) {
	var el = document.createElement( "fieldset" );

	try {
		return !!fn( el );
	} catch ( e ) {
		return false;
	} finally {

		// Remove from its parent by default
		if ( el.parentNode ) {
			el.parentNode.removeChild( el );
		}

		// release memory in IE
		el = null;
	}
}

/**
 * Returns a function to use in pseudos for input types
 * @param {String} type
 */
function createInputPseudo( type ) {
	return function( elem ) {
		return nodeName( elem, "input" ) && elem.type === type;
	};
}

/**
 * Returns a function to use in pseudos for buttons
 * @param {String} type
 */
function createButtonPseudo( type ) {
	return function( elem ) {
		return ( nodeName( elem, "input" ) || nodeName( elem, "button" ) ) &&
			elem.type === type;
	};
}

/**
 * Returns a function to use in pseudos for :enabled/:disabled
 * @param {Boolean} disabled true for :disabled; false for :enabled
 */
function createDisabledPseudo( disabled ) {

	// Known :disabled false positives: fieldset[disabled] > legend:nth-of-type(n+2) :can-disable
	return function( elem ) {

		// Only certain elements can match :enabled or :disabled
		// https://html.spec.whatwg.org/multipage/scripting.html#selector-enabled
		// https://html.spec.whatwg.org/multipage/scripting.html#selector-disabled
		if ( "form" in elem ) {

			// Check for inherited disabledness on relevant non-disabled elements:
			// * listed form-associated elements in a disabled fieldset
			//   https://html.spec.whatwg.org/multipage/forms.html#category-listed
			//   https://html.spec.whatwg.org/multipage/forms.html#concept-fe-disabled
			// * option elements in a disabled optgroup
			//   https://html.spec.whatwg.org/multipage/forms.html#concept-option-disabled
			// All such elements have a "form" property.
			if ( elem.parentNode && elem.disabled === false ) {

				// Option elements defer to a parent optgroup if present
				if ( "label" in elem ) {
					if ( "label" in elem.parentNode ) {
						return elem.parentNode.disabled === disabled;
					} else {
						return elem.disabled === disabled;
					}
				}

				// Support: IE 6 - 11+
				// Use the isDisabled shortcut property to check for disabled fieldset ancestors
				return elem.isDisabled === disabled ||

					// Where there is no isDisabled, check manually
					elem.isDisabled !== !disabled &&
						inDisabledFieldset( elem ) === disabled;
			}

			return elem.disabled === disabled;

		// Try to winnow out elements that can't be disabled before trusting the disabled property.
		// Some victims get caught in our net (label, legend, menu, track), but it shouldn't
		// even exist on them, let alone have a boolean value.
		} else if ( "label" in elem ) {
			return elem.disabled === disabled;
		}

		// Remaining elements are neither :enabled nor :disabled
		return false;
	};
}

/**
 * Returns a function to use in pseudos for positionals
 * @param {Function} fn
 */
function createPositionalPseudo( fn ) {
	return markFunction( function( argument ) {
		argument = +argument;
		return markFunction( function( seed, matches ) {
			var j,
				matchIndexes = fn( [], seed.length, argument ),
				i = matchIndexes.length;

			// Match elements found at the specified indexes
			while ( i-- ) {
				if ( seed[ ( j = matchIndexes[ i ] ) ] ) {
					seed[ j ] = !( matches[ j ] = seed[ j ] );
				}
			}
		} );
	} );
}

/**
 * Checks a node for validity as a jQuery selector context
 * @param {Element|Object=} context
 * @returns {Element|Object|Boolean} The input node if acceptable, otherwise a falsy value
 */
function testContext( context ) {
	return context && typeof context.getElementsByTagName !== "undefined" && context;
}

/**
 * Sets document-related variables once based on the current document
 * @param {Element|Object} [node] An element or document object to use to set the document
 * @returns {Object} Returns the current document
 */
function setDocument( node ) {
	var subWindow,
		doc = node ? node.ownerDocument || node : preferredDoc;

	// Return early if doc is invalid or already selected
	// Support: IE 11+, Edge 17 - 18+
	// IE/Edge sometimes throw a "Permission denied" error when strict-comparing
	// two documents; shallow comparisons work.
	// eslint-disable-next-line eqeqeq
	if ( doc == document || doc.nodeType !== 9 || !doc.documentElement ) {
		return document;
	}

	// Update global variables
	document = doc;
	documentElement = document.documentElement;
	documentIsHTML = !jQuery.isXMLDoc( document );

	// Support: iOS 7 only, IE 9 - 11+
	// Older browsers didn't support unprefixed `matches`.
	matches = documentElement.matches ||
		documentElement.webkitMatchesSelector ||
		documentElement.msMatchesSelector;

	// Support: IE 9 - 11+, Edge 12 - 18+
	// Accessing iframe documents after unload throws "permission denied" errors
	// (see trac-13936).
	// Limit the fix to IE & Edge Legacy; despite Edge 15+ implementing `matches`,
	// all IE 9+ and Edge Legacy versions implement `msMatchesSelector` as well.
	if ( documentElement.msMatchesSelector &&

		// Support: IE 11+, Edge 17 - 18+
		// IE/Edge sometimes throw a "Permission denied" error when strict-comparing
		// two documents; shallow comparisons work.
		// eslint-disable-next-line eqeqeq
		preferredDoc != document &&
		( subWindow = document.defaultView ) && subWindow.top !== subWindow ) {

		// Support: IE 9 - 11+, Edge 12 - 18+
		subWindow.addEventListener( "unload", unloadHandler );
	}

	// Support: IE <10
	// Check if getElementById returns elements by name
	// The broken getElementById methods don't pick up programmatically-set names,
	// so use a roundabout getElementsByName test
	support.getById = assert( function( el ) {
		documentElement.appendChild( el ).id = jQuery.expando;
		return !document.getElementsByName ||
			!document.getElementsByName( jQuery.expando ).length;
	} );

	// Support: IE 9 only
	// Check to see if it's possible to do matchesSelector
	// on a disconnected node.
	support.disconnectedMatch = assert( function( el ) {
		return matches.call( el, "*" );
	} );

	// Support: IE 9 - 11+, Edge 12 - 18+
	// IE/Edge don't support the :scope pseudo-class.
	support.scope = assert( function() {
		return document.querySelectorAll( ":scope" );
	} );

	// Support: Chrome 105 - 111 only, Safari 15.4 - 16.3 only
	// Make sure the `:has()` argument is parsed unforgivingly.
	// We include `*` in the test to detect buggy implementations that are
	// _selectively_ forgiving (specifically when the list includes at least
	// one valid selector).
	// Note that we treat complete lack of support for `:has()` as if it were
	// spec-compliant support, which is fine because use of `:has()` in such
	// environments will fail in the qSA path and fall back to jQuery traversal
	// anyway.
	support.cssHas = assert( function() {
		try {
			document.querySelector( ":has(*,:jqfake)" );
			return false;
		} catch ( e ) {
			return true;
		}
	} );

	// ID filter and find
	if ( support.getById ) {
		Expr.filter.ID = function( id ) {
			var attrId = id.replace( runescape, funescape );
			return function( elem ) {
				return elem.getAttribute( "id" ) === attrId;
			};
		};
		Expr.find.ID = function( id, context ) {
			if ( typeof context.getElementById !== "undefined" && documentIsHTML ) {
				var elem = context.getElementById( id );
				return elem ? [ elem ] : [];
			}
		};
	} else {
		Expr.filter.ID =  function( id ) {
			var attrId = id.replace( runescape, funescape );
			return function( elem ) {
				var node = typeof elem.getAttributeNode !== "undefined" &&
					elem.getAttributeNode( "id" );
				return node && node.value === attrId;
			};
		};

		// Support: IE 6 - 7 only
		// getElementById is not reliable as a find shortcut
		Expr.find.ID = function( id, context ) {
			if ( typeof context.getElementById !== "undefined" && documentIsHTML ) {
				var node, i, elems,
					elem = context.getElementById( id );

				if ( elem ) {

					// Verify the id attribute
					node = elem.getAttributeNode( "id" );
					if ( node && node.value === id ) {
						return [ elem ];
					}

					// Fall back on getElementsByName
					elems = context.getElementsByName( id );
					i = 0;
					while ( ( elem = elems[ i++ ] ) ) {
						node = elem.getAttributeNode( "id" );
						if ( node && node.value === id ) {
							return [ elem ];
						}
					}
				}

				return [];
			}
		};
	}

	// Tag
	Expr.find.TAG = function( tag, context ) {
		if ( typeof context.getElementsByTagName !== "undefined" ) {
			return context.getElementsByTagName( tag );

		// DocumentFragment nodes don't have gEBTN
		} else {
			return context.querySelectorAll( tag );
		}
	};

	// Class
	Expr.find.CLASS = function( className, context ) {
		if ( typeof context.getElementsByClassName !== "undefined" && documentIsHTML ) {
			return context.getElementsByClassName( className );
		}
	};

	/* QSA/matchesSelector
	---------------------------------------------------------------------- */

	// QSA and matchesSelector support

	rbuggyQSA = [];

	// Build QSA regex
	// Regex strategy adopted from Diego Perini
	assert( function( el ) {

		var input;

		documentElement.appendChild( el ).innerHTML =
			"<a id='" + expando + "' href='' disabled='disabled'></a>" +
			"<select id='" + expando + "-\r\\' disabled='disabled'>" +
			"<option selected=''></option></select>";

		// Support: iOS <=7 - 8 only
		// Boolean attributes and "value" are not treated correctly in some XML documents
		if ( !el.querySelectorAll( "[selected]" ).length ) {
			rbuggyQSA.push( "\\[" + whitespace + "*(?:value|" + booleans + ")" );
		}

		// Support: iOS <=7 - 8 only
		if ( !el.querySelectorAll( "[id~=" + expando + "-]" ).length ) {
			rbuggyQSA.push( "~=" );
		}

		// Support: iOS 8 only
		// https://bugs.webkit.org/show_bug.cgi?id=136851
		// In-page `selector#id sibling-combinator selector` fails
		if ( !el.querySelectorAll( "a#" + expando + "+*" ).length ) {
			rbuggyQSA.push( ".#.+[+~]" );
		}

		// Support: Chrome <=105+, Firefox <=104+, Safari <=15.4+
		// In some of the document kinds, these selectors wouldn't work natively.
		// This is probably OK but for backwards compatibility we want to maintain
		// handling them through jQuery traversal in jQuery 3.x.
		if ( !el.querySelectorAll( ":checked" ).length ) {
			rbuggyQSA.push( ":checked" );
		}

		// Support: Windows 8 Native Apps
		// The type and name attributes are restricted during .innerHTML assignment
		input = document.createElement( "input" );
		input.setAttribute( "type", "hidden" );
		el.appendChild( input ).setAttribute( "name", "D" );

		// Support: IE 9 - 11+
		// IE's :disabled selector does not pick up the children of disabled fieldsets
		// Support: Chrome <=105+, Firefox <=104+, Safari <=15.4+
		// In some of the document kinds, these selectors wouldn't work natively.
		// This is probably OK but for backwards compatibility we want to maintain
		// handling them through jQuery traversal in jQuery 3.x.
		documentElement.appendChild( el ).disabled = true;
		if ( el.querySelectorAll( ":disabled" ).length !== 2 ) {
			rbuggyQSA.push( ":enabled", ":disabled" );
		}

		// Support: IE 11+, Edge 15 - 18+
		// IE 11/Edge don't find elements on a `[name='']` query in some cases.
		// Adding a temporary attribute to the document before the selection works
		// around the issue.
		// Interestingly, IE 10 & older don't seem to have the issue.
		input = document.createElement( "input" );
		input.setAttribute( "name", "" );
		el.appendChild( input );
		if ( !el.querySelectorAll( "[name='']" ).length ) {
			rbuggyQSA.push( "\\[" + whitespace + "*name" + whitespace + "*=" +
				whitespace + "*(?:''|\"\")" );
		}
	} );

	if ( !support.cssHas ) {

		// Support: Chrome 105 - 110+, Safari 15.4 - 16.3+
		// Our regular `try-catch` mechanism fails to detect natively-unsupported
		// pseudo-classes inside `:has()` (such as `:has(:contains("Foo"))`)
		// in browsers that parse the `:has()` argument as a forgiving selector list.
		// https://drafts.csswg.org/selectors/#relational now requires the argument
		// to be parsed unforgivingly, but browsers have not yet fully adjusted.
		rbuggyQSA.push( ":has" );
	}

	rbuggyQSA = rbuggyQSA.length && new RegExp( rbuggyQSA.join( "|" ) );

	/* Sorting
	---------------------------------------------------------------------- */

	// Document order sorting
	sortOrder = function( a, b ) {

		// Flag for duplicate removal
		if ( a === b ) {
			hasDuplicate = true;
			return 0;
		}

		// Sort on method existence if only one input has compareDocumentPosition
		var compare = !a.compareDocumentPosition - !b.compareDocumentPosition;
		if ( compare ) {
			return compare;
		}

		// Calculate position if both inputs belong to the same document
		// Support: IE 11+, Edge 17 - 18+
		// IE/Edge sometimes throw a "Permission denied" error when strict-comparing
		// two documents; shallow comparisons work.
		// eslint-disable-next-line eqeqeq
		compare = ( a.ownerDocument || a ) == ( b.ownerDocument || b ) ?
			a.compareDocumentPosition( b ) :

			// Otherwise we know they are disconnected
			1;

		// Disconnected nodes
		if ( compare & 1 ||
			( !support.sortDetached && b.compareDocumentPosition( a ) === compare ) ) {

			// Choose the first element that is related to our preferred document
			// Support: IE 11+, Edge 17 - 18+
			// IE/Edge sometimes throw a "Permission denied" error when strict-comparing
			// two documents; shallow comparisons work.
			// eslint-disable-next-line eqeqeq
			if ( a === document || a.ownerDocument == preferredDoc &&
				find.contains( preferredDoc, a ) ) {
				return -1;
			}

			// Support: IE 11+, Edge 17 - 18+
			// IE/Edge sometimes throw a "Permission denied" error when strict-comparing
			// two documents; shallow comparisons work.
			// eslint-disable-next-line eqeqeq
			if ( b === document || b.ownerDocument == preferredDoc &&
				find.contains( preferredDoc, b ) ) {
				return 1;
			}

			// Maintain original order
			return sortInput ?
				( indexOf.call( sortInput, a ) - indexOf.call( sortInput, b ) ) :
				0;
		}

		return compare & 4 ? -1 : 1;
	};

	return document;
}

find.matches = function( expr, elements ) {
	return find( expr, null, null, elements );
};

find.matchesSelector = function( elem, expr ) {
	setDocument( elem );

	if ( documentIsHTML &&
		!nonnativeSelectorCache[ expr + " " ] &&
		( !rbuggyQSA || !rbuggyQSA.test( expr ) ) ) {

		try {
			var ret = matches.call( elem, expr );

			// IE 9's matchesSelector returns false on disconnected nodes
			if ( ret || support.disconnectedMatch ||

					// As well, disconnected nodes are said to be in a document
					// fragment in IE 9
					elem.document && elem.document.nodeType !== 11 ) {
				return ret;
			}
		} catch ( e ) {
			nonnativeSelectorCache( expr, true );
		}
	}

	return find( expr, document, null, [ elem ] ).length > 0;
};

find.contains = function( context, elem ) {

	// Set document vars if needed
	// Support: IE 11+, Edge 17 - 18+
	// IE/Edge sometimes throw a "Permission denied" error when strict-comparing
	// two documents; shallow comparisons work.
	// eslint-disable-next-line eqeqeq
	if ( ( context.ownerDocument || context ) != document ) {
		setDocument( context );
	}
	return jQuery.contains( context, elem );
};


find.attr = function( elem, name ) {

	// Set document vars if needed
	// Support: IE 11+, Edge 17 - 18+
	// IE/Edge sometimes throw a "Permission denied" error when strict-comparing
	// two documents; shallow comparisons work.
	// eslint-disable-next-line eqeqeq
	if ( ( elem.ownerDocument || elem ) != document ) {
		setDocument( elem );
	}

	var fn = Expr.attrHandle[ name.toLowerCase() ],

		// Don't get fooled by Object.prototype properties (see trac-13807)
		val = fn && hasOwn.call( Expr.attrHandle, name.toLowerCase() ) ?
			fn( elem, name, !documentIsHTML ) :
			undefined;

	if ( val !== undefined ) {
		return val;
	}

	return elem.getAttribute( name );
};

find.error = function( msg ) {
	throw new Error( "Syntax error, unrecognized expression: " + msg );
};

/**
 * Document sorting and removing duplicates
 * @param {ArrayLike} results
 */
jQuery.uniqueSort = function( results ) {
	var elem,
		duplicates = [],
		j = 0,
		i = 0;

	// Unless we *know* we can detect duplicates, assume their presence
	//
	// Support: Android <=4.0+
	// Testing for detecting duplicates is unpredictable so instead assume we can't
	// depend on duplicate detection in all browsers without a stable sort.
	hasDuplicate = !support.sortStable;
	sortInput = !support.sortStable && slice.call( results, 0 );
	sort.call( results, sortOrder );

	if ( hasDuplicate ) {
		while ( ( elem = results[ i++ ] ) ) {
			if ( elem === results[ i ] ) {
				j = duplicates.push( i );
			}
		}
		while ( j-- ) {
			splice.call( results, duplicates[ j ], 1 );
		}
	}

	// Clear input after sorting to release objects
	// See https://github.com/jquery/sizzle/pull/225
	sortInput = null;

	return results;
};

jQuery.fn.uniqueSort = function() {
	return this.pushStack( jQuery.uniqueSort( slice.apply( this ) ) );
};

Expr = jQuery.expr = {

	// Can be adjusted by the user
	cacheLength: 50,

	createPseudo: markFunction,

	match: matchExpr,

	attrHandle: {},

	find: {},

	relative: {
		">": { dir: "parentNode", first: true },
		" ": { dir: "parentNode" },
		"+": { dir: "previousSibling", first: true },
		"~": { dir: "previousSibling" }
	},

	preFilter: {
		ATTR: function( match ) {
			match[ 1 ] = match[ 1 ].replace( runescape, funescape );

			// Move the given value to match[3] whether quoted or unquoted
			match[ 3 ] = ( match[ 3 ] || match[ 4 ] || match[ 5 ] || "" )
				.replace( runescape, funescape );

			if ( match[ 2 ] === "~=" ) {
				match[ 3 ] = " " + match[ 3 ] + " ";
			}

			return match.slice( 0, 4 );
		},

		CHILD: function( match ) {

			/* matches from matchExpr["CHILD"]
				1 type (only|nth|...)
				2 what (child|of-type)
				3 argument (even|odd|\d*|\d*n([+-]\d+)?|...)
				4 xn-component of xn+y argument ([+-]?\d*n|)
				5 sign of xn-component
				6 x of xn-component
				7 sign of y-component
				8 y of y-component
			*/
			match[ 1 ] = match[ 1 ].toLowerCase();

			if ( match[ 1 ].slice( 0, 3 ) === "nth" ) {

				// nth-* requires argument
				if ( !match[ 3 ] ) {
					find.error( match[ 0 ] );
				}

				// numeric x and y parameters for Expr.filter.CHILD
				// remember that false/true cast respectively to 0/1
				match[ 4 ] = +( match[ 4 ] ?
					match[ 5 ] + ( match[ 6 ] || 1 ) :
					2 * ( match[ 3 ] === "even" || match[ 3 ] === "odd" )
				);
				match[ 5 ] = +( ( match[ 7 ] + match[ 8 ] ) || match[ 3 ] === "odd" );

			// other types prohibit arguments
			} else if ( match[ 3 ] ) {
				find.error( match[ 0 ] );
			}

			return match;
		},

		PSEUDO: function( match ) {
			var excess,
				unquoted = !match[ 6 ] && match[ 2 ];

			if ( matchExpr.CHILD.test( match[ 0 ] ) ) {
				return null;
			}

			// Accept quoted arguments as-is
			if ( match[ 3 ] ) {
				match[ 2 ] = match[ 4 ] || match[ 5 ] || "";

			// Strip excess characters from unquoted arguments
			} else if ( unquoted && rpseudo.test( unquoted ) &&

				// Get excess from tokenize (recursively)
				( excess = tokenize( unquoted, true ) ) &&

				// advance to the next closing parenthesis
				( excess = unquoted.indexOf( ")", unquoted.length - excess ) - unquoted.length ) ) {

				// excess is a negative index
				match[ 0 ] = match[ 0 ].slice( 0, excess );
				match[ 2 ] = unquoted.slice( 0, excess );
			}

			// Return only captures needed by the pseudo filter method (type and argument)
			return match.slice( 0, 3 );
		}
	},

	filter: {

		TAG: function( nodeNameSelector ) {
			var expectedNodeName = nodeNameSelector.replace( runescape, funescape ).toLowerCase();
			return nodeNameSelector === "*" ?
				function() {
					return true;
				} :
				function( elem ) {
					return nodeName( elem, expectedNodeName );
				};
		},

		CLASS: function( className ) {
			var pattern = classCache[ className + " " ];

			return pattern ||
				( pattern = new RegExp( "(^|" + whitespace + ")" + className +
					"(" + whitespace + "|$)" ) ) &&
				classCache( className, function( elem ) {
					return pattern.test(
						typeof elem.className === "string" && elem.className ||
							typeof elem.getAttribute !== "undefined" &&
								elem.getAttribute( "class" ) ||
							""
					);
				} );
		},

		ATTR: function( name, operator, check ) {
			return function( elem ) {
				var result = find.attr( elem, name );

				if ( result == null ) {
					return operator === "!=";
				}
				if ( !operator ) {
					return true;
				}

				result += "";

				if ( operator === "=" ) {
					return result === check;
				}
				if ( operator === "!=" ) {
					return result !== check;
				}
				if ( operator === "^=" ) {
					return check && result.indexOf( check ) === 0;
				}
				if ( operator === "*=" ) {
					return check && result.indexOf( check ) > -1;
				}
				if ( operator === "$=" ) {
					return check && result.slice( -check.length ) === check;
				}
				if ( operator === "~=" ) {
					return ( " " + result.replace( rwhitespace, " " ) + " " )
						.indexOf( check ) > -1;
				}
				if ( operator === "|=" ) {
					return result === check || result.slice( 0, check.length + 1 ) === check + "-";
				}

				return false;
			};
		},

		CHILD: function( type, what, _argument, first, last ) {
			var simple = type.slice( 0, 3 ) !== "nth",
				forward = type.slice( -4 ) !== "last",
				ofType = what === "of-type";

			return first === 1 && last === 0 ?

				// Shortcut for :nth-*(n)
				function( elem ) {
					return !!elem.parentNode;
				} :

				function( elem, _context, xml ) {
					var cache, outerCache, node, nodeIndex, start,
						dir = simple !== forward ? "nextSibling" : "previousSibling",
						parent = elem.parentNode,
						name = ofType && elem.nodeName.toLowerCase(),
						useCache = !xml && !ofType,
						diff = false;

					if ( parent ) {

						// :(first|last|only)-(child|of-type)
						if ( simple ) {
							while ( dir ) {
								node = elem;
								while ( ( node = node[ dir ] ) ) {
									if ( ofType ?
										nodeName( node, name ) :
										node.nodeType === 1 ) {

										return false;
									}
								}

								// Reverse direction for :only-* (if we haven't yet done so)
								start = dir = type === "only" && !start && "nextSibling";
							}
							return true;
						}

						start = [ forward ? parent.firstChild : parent.lastChild ];

						// non-xml :nth-child(...) stores cache data on `parent`
						if ( forward && useCache ) {

							// Seek `elem` from a previously-cached index
							outerCache = parent[ expando ] || ( parent[ expando ] = {} );
							cache = outerCache[ type ] || [];
							nodeIndex = cache[ 0 ] === dirruns && cache[ 1 ];
							diff = nodeIndex && cache[ 2 ];
							node = nodeIndex && parent.childNodes[ nodeIndex ];

							while ( ( node = ++nodeIndex && node && node[ dir ] ||

								// Fallback to seeking `elem` from the start
								( diff = nodeIndex = 0 ) || start.pop() ) ) {

								// When found, cache indexes on `parent` and break
								if ( node.nodeType === 1 && ++diff && node === elem ) {
									outerCache[ type ] = [ dirruns, nodeIndex, diff ];
									break;
								}
							}

						} else {

							// Use previously-cached element index if available
							if ( useCache ) {
								outerCache = elem[ expando ] || ( elem[ expando ] = {} );
								cache = outerCache[ type ] || [];
								nodeIndex = cache[ 0 ] === dirruns && cache[ 1 ];
								diff = nodeIndex;
							}

							// xml :nth-child(...)
							// or :nth-last-child(...) or :nth(-last)?-of-type(...)
							if ( diff === false ) {

								// Use the same loop as above to seek `elem` from the start
								while ( ( node = ++nodeIndex && node && node[ dir ] ||
									( diff = nodeIndex = 0 ) || start.pop() ) ) {

									if ( ( ofType ?
										nodeName( node, name ) :
										node.nodeType === 1 ) &&
										++diff ) {

										// Cache the index of each encountered element
										if ( useCache ) {
											outerCache = node[ expando ] ||
												( node[ expando ] = {} );
											outerCache[ type ] = [ dirruns, diff ];
										}

										if ( node === elem ) {
											break;
										}
									}
								}
							}
						}

						// Incorporate the offset, then check against cycle size
						diff -= last;
						return diff === first || ( diff % first === 0 && diff / first >= 0 );
					}
				};
		},

		PSEUDO: function( pseudo, argument ) {

			// pseudo-class names are case-insensitive
			// https://www.w3.org/TR/selectors/#pseudo-classes
			// Prioritize by case sensitivity in case custom pseudos are added with uppercase letters
			// Remember that setFilters inherits from pseudos
			var args,
				fn = Expr.pseudos[ pseudo ] || Expr.setFilters[ pseudo.toLowerCase() ] ||
					find.error( "unsupported pseudo: " + pseudo );

			// The user may use createPseudo to indicate that
			// arguments are needed to create the filter function
			// just as jQuery does
			if ( fn[ expando ] ) {
				return fn( argument );
			}

			// But maintain support for old signatures
			if ( fn.length > 1 ) {
				args = [ pseudo, pseudo, "", argument ];
				return Expr.setFilters.hasOwnProperty( pseudo.toLowerCase() ) ?
					markFunction( function( seed, matches ) {
						var idx,
							matched = fn( seed, argument ),
							i = matched.length;
						while ( i-- ) {
							idx = indexOf.call( seed, matched[ i ] );
							seed[ idx ] = !( matches[ idx ] = matched[ i ] );
						}
					} ) :
					function( elem ) {
						return fn( elem, 0, args );
					};
			}

			return fn;
		}
	},

	pseudos: {

		// Potentially complex pseudos
		not: markFunction( function( selector ) {

			// Trim the selector passed to compile
			// to avoid treating leading and trailing
			// spaces as combinators
			var input = [],
				results = [],
				matcher = compile( selector.replace( rtrimCSS, "$1" ) );

			return matcher[ expando ] ?
				markFunction( function( seed, matches, _context, xml ) {
					var elem,
						unmatched = matcher( seed, null, xml, [] ),
						i = seed.length;

					// Match elements unmatched by `matcher`
					while ( i-- ) {
						if ( ( elem = unmatched[ i ] ) ) {
							seed[ i ] = !( matches[ i ] = elem );
						}
					}
				} ) :
				function( elem, _context, xml ) {
					input[ 0 ] = elem;
					matcher( input, null, xml, results );

					// Don't keep the element
					// (see https://github.com/jquery/sizzle/issues/299)
					input[ 0 ] = null;
					return !results.pop();
				};
		} ),

		has: markFunction( function( selector ) {
			return function( elem ) {
				return find( selector, elem ).length > 0;
			};
		} ),

		contains: markFunction( function( text ) {
			text = text.replace( runescape, funescape );
			return function( elem ) {
				return ( elem.textContent || jQuery.text( elem ) ).indexOf( text ) > -1;
			};
		} ),

		// "Whether an element is represented by a :lang() selector
		// is based solely on the element's language value
		// being equal to the identifier C,
		// or beginning with the identifier C immediately followed by "-".
		// The matching of C against the element's language value is performed case-insensitively.
		// The identifier C does not have to be a valid language name."
		// https://www.w3.org/TR/selectors/#lang-pseudo
		lang: markFunction( function( lang ) {

			// lang value must be a valid identifier
			if ( !ridentifier.test( lang || "" ) ) {
				find.error( "unsupported lang: " + lang );
			}
			lang = lang.replace( runescape, funescape ).toLowerCase();
			return function( elem ) {
				var elemLang;
				do {
					if ( ( elemLang = documentIsHTML ?
						elem.lang :
						elem.getAttribute( "xml:lang" ) || elem.getAttribute( "lang" ) ) ) {

						elemLang = elemLang.toLowerCase();
						return elemLang === lang || elemLang.indexOf( lang + "-" ) === 0;
					}
				} while ( ( elem = elem.parentNode ) && elem.nodeType === 1 );
				return false;
			};
		} ),

		// Miscellaneous
		target: function( elem ) {
			var hash = window.location && window.location.hash;
			return hash && hash.slice( 1 ) === elem.id;
		},

		root: function( elem ) {
			return elem === documentElement;
		},

		focus: function( elem ) {
			return elem === safeActiveElement() &&
				document.hasFocus() &&
				!!( elem.type || elem.href || ~elem.tabIndex );
		},

		// Boolean properties
		enabled: createDisabledPseudo( false ),
		disabled: createDisabledPseudo( true ),

		checked: function( elem ) {

			// In CSS3, :checked should return both checked and selected elements
			// https://www.w3.org/TR/2011/REC-css3-selectors-20110929/#checked
			return ( nodeName( elem, "input" ) && !!elem.checked ) ||
				( nodeName( elem, "option" ) && !!elem.selected );
		},

		selected: function( elem ) {

			// Support: IE <=11+
			// Accessing the selectedIndex property
			// forces the browser to treat the default option as
			// selected when in an optgroup.
			if ( elem.parentNode ) {
				// eslint-disable-next-line no-unused-expressions
				elem.parentNode.selectedIndex;
			}

			return elem.selected === true;
		},

		// Contents
		empty: function( elem ) {

			// https://www.w3.org/TR/selectors/#empty-pseudo
			// :empty is negated by element (1) or content nodes (text: 3; cdata: 4; entity ref: 5),
			//   but not by others (comment: 8; processing instruction: 7; etc.)
			// nodeType < 6 works because attributes (2) do not appear as children
			for ( elem = elem.firstChild; elem; elem = elem.nextSibling ) {
				if ( elem.nodeType < 6 ) {
					return false;
				}
			}
			return true;
		},

		parent: function( elem ) {
			return !Expr.pseudos.empty( elem );
		},

		// Element/input types
		header: function( elem ) {
			return rheader.test( elem.nodeName );
		},

		input: function( elem ) {
			return rinputs.test( elem.nodeName );
		},

		button: function( elem ) {
			return nodeName( elem, "input" ) && elem.type === "button" ||
				nodeName( elem, "button" );
		},

		text: function( elem ) {
			var attr;
			return nodeName( elem, "input" ) && elem.type === "text" &&

				// Support: IE <10 only
				// New HTML5 attribute values (e.g., "search") appear
				// with elem.type === "text"
				( ( attr = elem.getAttribute( "type" ) ) == null ||
					attr.toLowerCase() === "text" );
		},

		// Position-in-collection
		first: createPositionalPseudo( function() {
			return [ 0 ];
		} ),

		last: createPositionalPseudo( function( _matchIndexes, length ) {
			return [ length - 1 ];
		} ),

		eq: createPositionalPseudo( function( _matchIndexes, length, argument ) {
			return [ argument < 0 ? argument + length : argument ];
		} ),

		even: createPositionalPseudo( function( matchIndexes, length ) {
			var i = 0;
			for ( ; i < length; i += 2 ) {
				matchIndexes.push( i );
			}
			return matchIndexes;
		} ),

		odd: createPositionalPseudo( function( matchIndexes, length ) {
			var i = 1;
			for ( ; i < length; i += 2 ) {
				matchIndexes.push( i );
			}
			return matchIndexes;
		} ),

		lt: createPositionalPseudo( function( matchIndexes, length, argument ) {
			var i;

			if ( argument < 0 ) {
				i = argument + length;
			} else if ( argument > length ) {
				i = length;
			} else {
				i = argument;
			}

			for ( ; --i >= 0; ) {
				matchIndexes.push( i );
			}
			return matchIndexes;
		} ),

		gt: createPositionalPseudo( function( matchIndexes, length, argument ) {
			var i = argument < 0 ? argument + length : argument;
			for ( ; ++i < length; ) {
				matchIndexes.push( i );
			}
			return matchIndexes;
		} )
	}
};

Expr.pseudos.nth = Expr.pseudos.eq;

// Add button/input type pseudos
for ( i in { radio: true, checkbox: true, file: true, password: true, image: true } ) {
	Expr.pseudos[ i ] = createInputPseudo( i );
}
for ( i in { submit: true, reset: true } ) {
	Expr.pseudos[ i ] = createButtonPseudo( i );
}

// Easy API for creating new setFilters
function setFilters() {}
setFilters.prototype = Expr.filters = Expr.pseudos;
Expr.setFilters = new setFilters();

function tokenize( selector, parseOnly ) {
	var matched, match, tokens, type,
		soFar, groups, preFilters,
		cached = tokenCache[ selector + " " ];

	if ( cached ) {
		return parseOnly ? 0 : cached.slice( 0 );
	}

	soFar = selector;
	groups = [];
	preFilters = Expr.preFilter;

	while ( soFar ) {

		// Comma and first run
		if ( !matched || ( match = rcomma.exec( soFar ) ) ) {
			if ( match ) {

				// Don't consume trailing commas as valid
				soFar = soFar.slice( match[ 0 ].length ) || soFar;
			}
			groups.push( ( tokens = [] ) );
		}

		matched = false;

		// Combinators
		if ( ( match = rleadingCombinator.exec( soFar ) ) ) {
			matched = match.shift();
			tokens.push( {
				value: matched,

				// Cast descendant combinators to space
				type: match[ 0 ].replace( rtrimCSS, " " )
			} );
			soFar = soFar.slice( matched.length );
		}

		// Filters
		for ( type in Expr.filter ) {
			if ( ( match = matchExpr[ type ].exec( soFar ) ) && ( !preFilters[ type ] ||
				( match = preFilters[ type ]( match ) ) ) ) {
				matched = match.shift();
				tokens.push( {
					value: matched,
					type: type,
					matches: match
				} );
				soFar = soFar.slice( matched.length );
			}
		}

		if ( !matched ) {
			break;
		}
	}

	// Return the length of the invalid excess
	// if we're just parsing
	// Otherwise, throw an error or return tokens
	if ( parseOnly ) {
		return soFar.length;
	}

	return soFar ?
		find.error( selector ) :

		// Cache the tokens
		tokenCache( selector, groups ).slice( 0 );
}

function toSelector( tokens ) {
	var i = 0,
		len = tokens.length,
		selector = "";
	for ( ; i < len; i++ ) {
		selector += tokens[ i ].value;
	}
	return selector;
}

function addCombinator( matcher, combinator, base ) {
	var dir = combinator.dir,
		skip = combinator.next,
		key = skip || dir,
		checkNonElements = base && key === "parentNode",
		doneName = done++;

	return combinator.first ?

		// Check against closest ancestor/preceding element
		function( elem, context, xml ) {
			while ( ( elem = elem[ dir ] ) ) {
				if ( elem.nodeType === 1 || checkNonElements ) {
					return matcher( elem, context, xml );
				}
			}
			return false;
		} :

		// Check against all ancestor/preceding elements
		function( elem, context, xml ) {
			var oldCache, outerCache,
				newCache = [ dirruns, doneName ];

			// We can't set arbitrary data on XML nodes, so they don't benefit from combinator caching
			if ( xml ) {
				while ( ( elem = elem[ dir ] ) ) {
					if ( elem.nodeType === 1 || checkNonElements ) {
						if ( matcher( elem, context, xml ) ) {
							return true;
						}
					}
				}
			} else {
				while ( ( elem = elem[ dir ] ) ) {
					if ( elem.nodeType === 1 || checkNonElements ) {
						outerCache = elem[ expando ] || ( elem[ expando ] = {} );

						if ( skip && nodeName( elem, skip ) ) {
							elem = elem[ dir ] || elem;
						} else if ( ( oldCache = outerCache[ key ] ) &&
							oldCache[ 0 ] === dirruns && oldCache[ 1 ] === doneName ) {

							// Assign to newCache so results back-propagate to previous elements
							return ( newCache[ 2 ] = oldCache[ 2 ] );
						} else {

							// Reuse newcache so results back-propagate to previous elements
							outerCache[ key ] = newCache;

							// A match means we're done; a fail means we have to keep checking
							if ( ( newCache[ 2 ] = matcher( elem, context, xml ) ) ) {
								return true;
							}
						}
					}
				}
			}
			return false;
		};
}

function elementMatcher( matchers ) {
	return matchers.length > 1 ?
		function( elem, context, xml ) {
			var i = matchers.length;
			while ( i-- ) {
				if ( !matchers[ i ]( elem, context, xml ) ) {
					return false;
				}
			}
			return true;
		} :
		matchers[ 0 ];
}

function multipleContexts( selector, contexts, results ) {
	var i = 0,
		len = contexts.length;
	for ( ; i < len; i++ ) {
		find( selector, contexts[ i ], results );
	}
	return results;
}

function condense( unmatched, map, filter, context, xml ) {
	var elem,
		newUnmatched = [],
		i = 0,
		len = unmatched.length,
		mapped = map != null;

	for ( ; i < len; i++ ) {
		if ( ( elem = unmatched[ i ] ) ) {
			if ( !filter || filter( elem, context, xml ) ) {
				newUnmatched.push( elem );
				if ( mapped ) {
					map.push( i );
				}
			}
		}
	}

	return newUnmatched;
}

function setMatcher( preFilter, selector, matcher, postFilter, postFinder, postSelector ) {
	if ( postFilter && !postFilter[ expando ] ) {
		postFilter = setMatcher( postFilter );
	}
	if ( postFinder && !postFinder[ expando ] ) {
		postFinder = setMatcher( postFinder, postSelector );
	}
	return markFunction( function( seed, results, context, xml ) {
		var temp, i, elem, matcherOut,
			preMap = [],
			postMap = [],
			preexisting = results.length,

			// Get initial elements from seed or context
			elems = seed ||
				multipleContexts( selector || "*",
					context.nodeType ? [ context ] : context, [] ),

			// Prefilter to get matcher input, preserving a map for seed-results synchronization
			matcherIn = preFilter && ( seed || !selector ) ?
				condense( elems, preMap, preFilter, context, xml ) :
				elems;

		if ( matcher ) {

			// If we have a postFinder, or filtered seed, or non-seed postFilter
			// or preexisting results,
			matcherOut = postFinder || ( seed ? preFilter : preexisting || postFilter ) ?

				// ...intermediate processing is necessary
				[] :

				// ...otherwise use results directly
				results;

			// Find primary matches
			matcher( matcherIn, matcherOut, context, xml );
		} else {
			matcherOut = matcherIn;
		}

		// Apply postFilter
		if ( postFilter ) {
			temp = condense( matcherOut, postMap );
			postFilter( temp, [], context, xml );

			// Un-match failing elements by moving them back to matcherIn
			i = temp.length;
			while ( i-- ) {
				if ( ( elem = temp[ i ] ) ) {
					matcherOut[ postMap[ i ] ] = !( matcherIn[ postMap[ i ] ] = elem );
				}
			}
		}

		if ( seed ) {
			if ( postFinder || preFilter ) {
				if ( postFinder ) {

					// Get the final matcherOut by condensing this intermediate into postFinder contexts
					temp = [];
					i = matcherOut.length;
					while ( i-- ) {
						if ( ( elem = matcherOut[ i ] ) ) {

							// Restore matcherIn since elem is not yet a final match
							temp.push( ( matcherIn[ i ] = elem ) );
						}
					}
					postFinder( null, ( matcherOut = [] ), temp, xml );
				}

				// Move matched elements from seed to results to keep them synchronized
				i = matcherOut.length;
				while ( i-- ) {
					if ( ( elem = matcherOut[ i ] ) &&
						( temp = postFinder ? indexOf.call( seed, elem ) : preMap[ i ] ) > -1 ) {

						seed[ temp ] = !( results[ temp ] = elem );
					}
				}
			}

		// Add elements to results, through postFinder if defined
		} else {
			matcherOut = condense(
				matcherOut === results ?
					matcherOut.splice( preexisting, matcherOut.length ) :
					matcherOut
			);
			if ( postFinder ) {
				postFinder( null, results, matcherOut, xml );
			} else {
				push.apply( results, matcherOut );
			}
		}
	} );
}

function matcherFromTokens( tokens ) {
	var checkContext, matcher, j,
		len = tokens.length,
		leadingRelative = Expr.relative[ tokens[ 0 ].type ],
		implicitRelative = leadingRelative || Expr.relative[ " " ],
		i = leadingRelative ? 1 : 0,

		// The foundational matcher ensures that elements are reachable from top-level context(s)
		matchContext = addCombinator( function( elem ) {
			return elem === checkContext;
		}, implicitRelative, true ),
		matchAnyContext = addCombinator( function( elem ) {
			return indexOf.call( checkContext, elem ) > -1;
		}, implicitRelative, true ),
		matchers = [ function( elem, context, xml ) {

			// Support: IE 11+, Edge 17 - 18+
			// IE/Edge sometimes throw a "Permission denied" error when strict-comparing
			// two documents; shallow comparisons work.
			// eslint-disable-next-line eqeqeq
			var ret = ( !leadingRelative && ( xml || context != outermostContext ) ) || (
				( checkContext = context ).nodeType ?
					matchContext( elem, context, xml ) :
					matchAnyContext( elem, context, xml ) );

			// Avoid hanging onto element
			// (see https://github.com/jquery/sizzle/issues/299)
			checkContext = null;
			return ret;
		} ];

	for ( ; i < len; i++ ) {
		if ( ( matcher = Expr.relative[ tokens[ i ].type ] ) ) {
			matchers = [ addCombinator( elementMatcher( matchers ), matcher ) ];
		} else {
			matcher = Expr.filter[ tokens[ i ].type ].apply( null, tokens[ i ].matches );

			// Return special upon seeing a positional matcher
			if ( matcher[ expando ] ) {

				// Find the next relative operator (if any) for proper handling
				j = ++i;
				for ( ; j < len; j++ ) {
					if ( Expr.relative[ tokens[ j ].type ] ) {
						break;
					}
				}
				return setMatcher(
					i > 1 && elementMatcher( matchers ),
					i > 1 && toSelector(

						// If the preceding token was a descendant combinator, insert an implicit any-element `*`
						tokens.slice( 0, i - 1 )
							.concat( { value: tokens[ i - 2 ].type === " " ? "*" : "" } )
					).replace( rtrimCSS, "$1" ),
					matcher,
					i < j && matcherFromTokens( tokens.slice( i, j ) ),
					j < len && matcherFromTokens( ( tokens = tokens.slice( j ) ) ),
					j < len && toSelector( tokens )
				);
			}
			matchers.push( matcher );
		}
	}

	return elementMatcher( matchers );
}

function matcherFromGroupMatchers( elementMatchers, setMatchers ) {
	var bySet = setMatchers.length > 0,
		byElement = elementMatchers.length > 0,
		superMatcher = function( seed, context, xml, results, outermost ) {
			var elem, j, matcher,
				matchedCount = 0,
				i = "0",
				unmatched = seed && [],
				setMatched = [],
				contextBackup = outermostContext,

				// We must always have either seed elements or outermost context
				elems = seed || byElement && Expr.find.TAG( "*", outermost ),

				// Use integer dirruns iff this is the outermost matcher
				dirrunsUnique = ( dirruns += contextBackup == null ? 1 : Math.random() || 0.1 ),
				len = elems.length;

			if ( outermost ) {

				// Support: IE 11+, Edge 17 - 18+
				// IE/Edge sometimes throw a "Permission denied" error when strict-comparing
				// two documents; shallow comparisons work.
				// eslint-disable-next-line eqeqeq
				outermostContext = context == document || context || outermost;
			}

			// Add elements passing elementMatchers directly to results
			// Support: iOS <=7 - 9 only
			// Tolerate NodeList properties (IE: "length"; Safari: <number>) matching
			// elements by id. (see trac-14142)
			for ( ; i !== len && ( elem = elems[ i ] ) != null; i++ ) {
				if ( byElement && elem ) {
					j = 0;

					// Support: IE 11+, Edge 17 - 18+
					// IE/Edge sometimes throw a "Permission denied" error when strict-comparing
					// two documents; shallow comparisons work.
					// eslint-disable-next-line eqeqeq
					if ( !context && elem.ownerDocument != document ) {
						setDocument( elem );
						xml = !documentIsHTML;
					}
					while ( ( matcher = elementMatchers[ j++ ] ) ) {
						if ( matcher( elem, context || document, xml ) ) {
							push.call( results, elem );
							break;
						}
					}
					if ( outermost ) {
						dirruns = dirrunsUnique;
					}
				}

				// Track unmatched elements for set filters
				if ( bySet ) {

					// They will have gone through all possible matchers
					if ( ( elem = !matcher && elem ) ) {
						matchedCount--;
					}

					// Lengthen the array for every element, matched or not
					if ( seed ) {
						unmatched.push( elem );
					}
				}
			}

			// `i` is now the count of elements visited above, and adding it to `matchedCount`
			// makes the latter nonnegative.
			matchedCount += i;

			// Apply set filters to unmatched elements
			// NOTE: This can be skipped if there are no unmatched elements (i.e., `matchedCount`
			// equals `i`), unless we didn't visit _any_ elements in the above loop because we have
			// no element matchers and no seed.
			// Incrementing an initially-string "0" `i` allows `i` to remain a string only in that
			// case, which will result in a "00" `matchedCount` that differs from `i` but is also
			// numerically zero.
			if ( bySet && i !== matchedCount ) {
				j = 0;
				while ( ( matcher = setMatchers[ j++ ] ) ) {
					matcher( unmatched, setMatched, context, xml );
				}

				if ( seed ) {

					// Reintegrate element matches to eliminate the need for sorting
					if ( matchedCount > 0 ) {
						while ( i-- ) {
							if ( !( unmatched[ i ] || setMatched[ i ] ) ) {
								setMatched[ i ] = pop.call( results );
							}
						}
					}

					// Discard index placeholder values to get only actual matches
					setMatched = condense( setMatched );
				}

				// Add matches to results
				push.apply( results, setMatched );

				// Seedless set matches succeeding multiple successful matchers stipulate sorting
				if ( outermost && !seed && setMatched.length > 0 &&
					( matchedCount + setMatchers.length ) > 1 ) {

					jQuery.uniqueSort( results );
				}
			}

			// Override manipulation of globals by nested matchers
			if ( outermost ) {
				dirruns = dirrunsUnique;
				outermostContext = contextBackup;
			}

			return unmatched;
		};

	return bySet ?
		markFunction( superMatcher ) :
		superMatcher;
}

function compile( selector, match /* Internal Use Only */ ) {
	var i,
		setMatchers = [],
		elementMatchers = [],
		cached = compilerCache[ selector + " " ];

	if ( !cached ) {

		// Generate a function of recursive functions that can be used to check each element
		if ( !match ) {
			match = tokenize( selector );
		}
		i = match.length;
		while ( i-- ) {
			cached = matcherFromTokens( match[ i ] );
			if ( cached[ expando ] ) {
				setMatchers.push( cached );
			} else {
				elementMatchers.push( cached );
			}
		}

		// Cache the compiled function
		cached = compilerCache( selector,
			matcherFromGroupMatchers( elementMatchers, setMatchers ) );

		// Save selector and tokenization
		cached.selector = selector;
	}
	return cached;
}

/**
 * A low-level selection function that works with jQuery's compiled
 *  selector functions
 * @param {String|Function} selector A selector or a pre-compiled
 *  selector function built with jQuery selector compile
 * @param {Element} context
 * @param {Array} [results]
 * @param {Array} [seed] A set of elements to match against
 */
function select( selector, context, results, seed ) {
	var i, tokens, token, type, find,
		compiled = typeof selector === "function" && selector,
		match = !seed && tokenize( ( selector = compiled.selector || selector ) );

	results = results || [];

	// Try to minimize operations if there is only one selector in the list and no seed
	// (the latter of which guarantees us context)
	if ( match.length === 1 ) {

		// Reduce context if the leading compound selector is an ID
		tokens = match[ 0 ] = match[ 0 ].slice( 0 );
		if ( tokens.length > 2 && ( token = tokens[ 0 ] ).type === "ID" &&
				context.nodeType === 9 && documentIsHTML && Expr.relative[ tokens[ 1 ].type ] ) {

			context = ( Expr.find.ID(
				token.matches[ 0 ].replace( runescape, funescape ),
				context
			) || [] )[ 0 ];
			if ( !context ) {
				return results;

			// Precompiled matchers will still verify ancestry, so step up a level
			} else if ( compiled ) {
				context = context.parentNode;
			}

			selector = selector.slice( tokens.shift().value.length );
		}

		// Fetch a seed set for right-to-left matching
		i = matchExpr.needsContext.test( selector ) ? 0 : tokens.length;
		while ( i-- ) {
			token = tokens[ i ];

			// Abort if we hit a combinator
			if ( Expr.relative[ ( type = token.type ) ] ) {
				break;
			}
			if ( ( find = Expr.find[ type ] ) ) {

				// Search, expanding context for leading sibling combinators
				if ( ( seed = find(
					token.matches[ 0 ].replace( runescape, funescape ),
					rsibling.test( tokens[ 0 ].type ) &&
						testContext( context.parentNode ) || context
				) ) ) {

					// If seed is empty or no tokens remain, we can return early
					tokens.splice( i, 1 );
					selector = seed.length && toSelector( tokens );
					if ( !selector ) {
						push.apply( results, seed );
						return results;
					}

					break;
				}
			}
		}
	}

	// Compile and execute a filtering function if one is not provided
	// Provide `match` to avoid retokenization if we modified the selector above
	( compiled || compile( selector, match ) )(
		seed,
		context,
		!documentIsHTML,
		results,
		!context || rsibling.test( selector ) && testContext( context.parentNode ) || context
	);
	return results;
}

// One-time assignments

// Support: Android <=4.0 - 4.1+
// Sort stability
support.sortStable = expando.split( "" ).sort( sortOrder ).join( "" ) === expando;

// Initialize against the default document
setDocument();

// Support: Android <=4.0 - 4.1+
// Detached nodes confoundingly follow *each other*
support.sortDetached = assert( function( el ) {

	// Should return 1, but returns 4 (following)
	return el.compareDocumentPosition( document.createElement( "fieldset" ) ) & 1;
} );

jQuery.find = find;

// Deprecated
jQuery.expr[ ":" ] = jQuery.expr.pseudos;
jQuery.unique = jQuery.uniqueSort;

// These have always been private, but they used to be documented as part of
// Sizzle so let's maintain them for now for backwards compatibility purposes.
find.compile = compile;
find.select = select;
find.setDocument = setDocument;
find.tokenize = tokenize;

find.escape = jQuery.escapeSelector;
find.getText = jQuery.text;
find.isXML = jQuery.isXMLDoc;
find.selectors = jQuery.expr;
find.support = jQuery.support;
find.uniqueSort = jQuery.uniqueSort;

	/* eslint-enable */

} )();


var dir = function( elem, dir, until ) {
	var matched = [],
		truncate = until !== undefined;

	while ( ( elem = elem[ dir ] ) && elem.nodeType !== 9 ) {
		if ( elem.nodeType === 1 ) {
			if ( truncate && jQuery( elem ).is( until ) ) {
				break;
			}
			matched.push( elem );
		}
	}
	return matched;
};


var siblings = function( n, elem ) {
	var matched = [];

	for ( ; n; n = n.nextSibling ) {
		if ( n.nodeType === 1 && n !== elem ) {
			matched.push( n );
		}
	}

	return matched;
};


var rneedsContext = jQuery.expr.match.needsContext;

var rsingleTag = ( /^<([a-z][^\/\0>:\x20\t\r\n\f]*)[\x20\t\r\n\f]*\/?>(?:<\/\1>|)$/i );



// Implement the identical functionality for filter and not
function winnow( elements, qualifier, not ) {
	if ( isFunction( qualifier ) ) {
		return jQuery.grep( elements, function( elem, i ) {
			return !!qualifier.call( elem, i, elem ) !== not;
		} );
	}

	// Single element
	if ( qualifier.nodeType ) {
		return jQuery.grep( elements, function( elem ) {
			return ( elem === qualifier ) !== not;
		} );
	}

	// Arraylike of elements (jQuery, arguments, Array)
	if ( typeof qualifier !== "string" ) {
		return jQuery.grep( elements, function( elem ) {
			return ( indexOf.call( qualifier, elem ) > -1 ) !== not;
		} );
	}

	// Filtered directly for both simple and complex selectors
	return jQuery.filter( qualifier, elements, not );
}

jQuery.filter = function( expr, elems, not ) {
	var elem = elems[ 0 ];

	if ( not ) {
		expr = ":not(" + expr + ")";
	}

	if ( elems.length === 1 && elem.nodeType === 1 ) {
		return jQuery.find.matchesSelector( elem, expr ) ? [ elem ] : [];
	}

	return jQuery.find.matches( expr, jQuery.grep( elems, function( elem ) {
		return elem.nodeType === 1;
	} ) );
};

jQuery.fn.extend( {
	find: function( selector ) {
		var i, ret,
			len = this.length,
			self = this;

		if ( typeof selector !== "string" ) {
			return this.pushStack( jQuery( selector ).filter( function() {
				for ( i = 0; i < len; i++ ) {
					if ( jQuery.contains( self[ i ], this ) ) {
						return true;
					}
				}
			} ) );
		}

		ret = this.pushStack( [] );

		for ( i = 0; i < len; i++ ) {
			jQuery.find( selector, self[ i ], ret );
		}

		return len > 1 ? jQuery.uniqueSort( ret ) : ret;
	},
	filter: function( selector ) {
		return this.pushStack( winnow( this, selector || [], false ) );
	},
	not: function( selector ) {
		return this.pushStack( winnow( this, selector || [], true ) );
	},
	is: function( selector ) {
		return !!winnow(
			this,

			// If this is a positional/relative selector, check membership in the returned set
			// so $("p:first").is("p:last") won't return true for a doc with two "p".
			typeof selector === "string" && rneedsContext.test( selector ) ?
				jQuery( selector ) :
				selector || [],
			false
		).length;
	}
} );


// Initialize a jQuery object


// A central reference to the root jQuery(document)
var rootjQuery,

	// A simple way to check for HTML strings
	// Prioritize #id over <tag> to avoid XSS via location.hash (trac-9521)
	// Strict HTML recognition (trac-11290: must start with <)
	// Shortcut simple #id case for speed
	rquickExpr = /^(?:\s*(<[\w\W]+>)[^>]*|#([\w-]+))$/,

	init = jQuery.fn.init = function( selector, context, root ) {
		var match, elem;

		// HANDLE: $(""), $(null), $(undefined), $(false)
		if ( !selector ) {
			return this;
		}

		// Method init() accepts an alternate rootjQuery
		// so migrate can support jQuery.sub (gh-2101)
		root = root || rootjQuery;

		// Handle HTML strings
		if ( typeof selector === "string" ) {
			if ( selector[ 0 ] === "<" &&
				selector[ selector.length - 1 ] === ">" &&
				selector.length >= 3 ) {

				// Assume that strings that start and end with <> are HTML and skip the regex check
				match = [ null, selector, null ];

			} else {
				match = rquickExpr.exec( selector );
			}

			// Match html or make sure no context is specified for #id
			if ( match && ( match[ 1 ] || !context ) ) {

				// HANDLE: $(html) -> $(array)
				if ( match[ 1 ] ) {
					context = context instanceof jQuery ? context[ 0 ] : context;

					// Option to run scripts is true for back-compat
					// Intentionally let the error be thrown if parseHTML is not present
					jQuery.merge( this, jQuery.parseHTML(
						match[ 1 ],
						context && context.nodeType ? context.ownerDocument || context : document,
						true
					) );

					// HANDLE: $(html, props)
					if ( rsingleTag.test( match[ 1 ] ) && jQuery.isPlainObject( context ) ) {
						for ( match in context ) {

							// Properties of context are called as methods if possible
							if ( isFunction( this[ match ] ) ) {
								this[ match ]( context[ match ] );

							// ...and otherwise set as attributes
							} else {
								this.attr( match, context[ match ] );
							}
						}
					}

					return this;

				// HANDLE: $(#id)
				} else {
					elem = document.getElementById( match[ 2 ] );

					if ( elem ) {

						// Inject the element directly into the jQuery object
						this[ 0 ] = elem;
						this.length = 1;
					}
					return this;
				}

			// HANDLE: $(expr, $(...))
			} else if ( !context || context.jquery ) {
				return ( context || root ).find( selector );

			// HANDLE: $(expr, context)
			// (which is just equivalent to: $(context).find(expr)
			} else {
				return this.constructor( context ).find( selector );
			}

		// HANDLE: $(DOMElement)
		} else if ( selector.nodeType ) {
			this[ 0 ] = selector;
			this.length = 1;
			return this;

		// HANDLE: $(function)
		// Shortcut for document ready
		} else if ( isFunction( selector ) ) {
			return root.ready !== undefined ?
				root.ready( selector ) :

				// Execute immediately if ready is not present
				selector( jQuery );
		}

		return jQuery.makeArray( selector, this );
	};

// Give the init function the jQuery prototype for later instantiation
init.prototype = jQuery.fn;

// Initialize central reference
rootjQuery = jQuery( document );


var rparentsprev = /^(?:parents|prev(?:Until|All))/,

	// Methods guaranteed to produce a unique set when starting from a unique set
	guaranteedUnique = {
		children: true,
		contents: true,
		next: true,
		prev: true
	};

jQuery.fn.extend( {
	has: function( target ) {
		var targets = jQuery( target, this ),
			l = targets.length;

		return this.filter( function() {
			var i = 0;
			for ( ; i < l; i++ ) {
				if ( jQuery.contains( this, targets[ i ] ) ) {
					return true;
				}
			}
		} );
	},

	closest: function( selectors, context ) {
		var cur,
			i = 0,
			l = this.length,
			matched = [],
			targets = typeof selectors !== "string" && jQuery( selectors );

		// Positional selectors never match, since there's no _selection_ context
		if ( !rneedsContext.test( selectors ) ) {
			for ( ; i < l; i++ ) {
				for ( cur = this[ i ]; cur && cur !== context; cur = cur.parentNode ) {

					// Always skip document fragments
					if ( cur.nodeType < 11 && ( targets ?
						targets.index( cur ) > -1 :

						// Don't pass non-elements to jQuery#find
						cur.nodeType === 1 &&
							jQuery.find.matchesSelector( cur, selectors ) ) ) {

						matched.push( cur );
						break;
					}
				}
			}
		}

		return this.pushStack( matched.length > 1 ? jQuery.uniqueSort( matched ) : matched );
	},

	// Determine the position of an element within the set
	index: function( elem ) {

		// No argument, return index in parent
		if ( !elem ) {
			return ( this[ 0 ] && this[ 0 ].parentNode ) ? this.first().prevAll().length : -1;
		}

		// Index in selector
		if ( typeof elem === "string" ) {
			return indexOf.call( jQuery( elem ), this[ 0 ] );
		}

		// Locate the position of the desired element
		return indexOf.call( this,

			// If it receives a jQuery object, the first element is used
			elem.jquery ? elem[ 0 ] : elem
		);
	},

	add: function( selector, context ) {
		return this.pushStack(
			jQuery.uniqueSort(
				jQuery.merge( this.get(), jQuery( selector, context ) )
			)
		);
	},

	addBack: function( selector ) {
		return this.add( selector == null ?
			this.prevObject : this.prevObject.filter( selector )
		);
	}
} );

function sibling( cur, dir ) {
	while ( ( cur = cur[ dir ] ) && cur.nodeType !== 1 ) {}
	return cur;
}

jQuery.each( {
	parent: function( elem ) {
		var parent = elem.parentNode;
		return parent && parent.nodeType !== 11 ? parent : null;
	},
	parents: function( elem ) {
		return dir( elem, "parentNode" );
	},
	parentsUntil: function( elem, _i, until ) {
		return dir( elem, "parentNode", until );
	},
	next: function( elem ) {
		return sibling( elem, "nextSibling" );
	},
	prev: function( elem ) {
		return sibling( elem, "previousSibling" );
	},
	nextAll: function( elem ) {
		return dir( elem, "nextSibling" );
	},
	prevAll: function( elem ) {
		return dir( elem, "previousSibling" );
	},
	nextUntil: function( elem, _i, until ) {
		return dir( elem, "nextSibling", until );
	},
	prevUntil: function( elem, _i, until ) {
		return dir( elem, "previousSibling", until );
	},
	siblings: function( elem ) {
		return siblings( ( elem.parentNode || {} ).firstChild, elem );
	},
	children: function( elem ) {
		return siblings( elem.firstChild );
	},
	contents: function( elem ) {
		if ( elem.contentDocument != null &&

			// Support: IE 11+
			// <object> elements with no `data` attribute has an object
			// `contentDocument` with a `null` prototype.
			getProto( elem.contentDocument ) ) {

			return elem.contentDocument;
		}

		// Support: IE 9 - 11 only, iOS 7 only, Android Browser <=4.3 only
		// Treat the template element as a regular one in browsers that
		// don't support it.
		if ( nodeName( elem, "template" ) ) {
			elem = elem.content || elem;
		}

		return jQuery.merge( [], elem.childNodes );
	}
}, function( name, fn ) {
	jQuery.fn[ name ] = function( until, selector ) {
		var matched = jQuery.map( this, fn, until );

		if ( name.slice( -5 ) !== "Until" ) {
			selector = until;
		}

		if ( selector && typeof selector === "string" ) {
			matched = jQuery.filter( selector, matched );
		}

		if ( this.length > 1 ) {

			// Remove duplicates
			if ( !guaranteedUnique[ name ] ) {
				jQuery.uniqueSort( matched );
			}

			// Reverse order for parents* and prev-derivatives
			if ( rparentsprev.test( name ) ) {
				matched.reverse();
			}
		}

		return this.pushStack( matched );
	};
} );
var rnothtmlwhite = ( /[^\x20\t\r\n\f]+/g );



// Convert String-formatted options into Object-formatted ones
function createOptions( options ) {
	var object = {};
	jQuery.each( options.match( rnothtmlwhite ) || [], function( _, flag ) {
		object[ flag ] = true;
	} );
	return object;
}

/*
 * Create a callback list using the following parameters:
 *
 *	options: an optional list of space-separated options that will change how
 *			the callback list behaves or a more traditional option object
 *
 * By default a callback list will act like an event callback list and can be
 * "fired" multiple times.
 *
 * Possible options:
 *
 *	once:			will ensure the callback list can only be fired once (like a Deferred)
 *
 *	memory:			will keep track of previous values and will call any callback added
 *					after the list has been fired right away with the latest "memorized"
 *					values (like a Deferred)
 *
 *	unique:			will ensure a callback can only be added once (no duplicate in the list)
 *
 *	stopOnFalse:	interrupt callings when a callback returns false
 *
 */
jQuery.Callbacks = function( options ) {

	// Convert options from String-formatted to Object-formatted if needed
	// (we check in cache first)
	options = typeof options === "string" ?
		createOptions( options ) :
		jQuery.extend( {}, options );

	var // Flag to know if list is currently firing
		firing,

		// Last fire value for non-forgettable lists
		memory,

		// Flag to know if list was already fired
		fired,

		// Flag to prevent firing
		locked,

		// Actual callback list
		list = [],

		// Queue of execution data for repeatable lists
		queue = [],

		// Index of currently firing callback (modified by add/remove as needed)
		firingIndex = -1,

		// Fire callbacks
		fire = function() {

			// Enforce single-firing
			locked = locked || options.once;

			// Execute callbacks for all pending executions,
			// respecting firingIndex overrides and runtime changes
			fired = firing = true;
			for ( ; queue.length; firingIndex = -1 ) {
				memory = queue.shift();
				while ( ++firingIndex < list.length ) {

					// Run callback and check for early termination
					if ( list[ firingIndex ].apply( memory[ 0 ], memory[ 1 ] ) === false &&
						options.stopOnFalse ) {

						// Jump to end and forget the data so .add doesn't re-fire
						firingIndex = list.length;
						memory = false;
					}
				}
			}

			// Forget the data if we're done with it
			if ( !options.memory ) {
				memory = false;
			}

			firing = false;

			// Clean up if we're done firing for good
			if ( locked ) {

				// Keep an empty list if we have data for future add calls
				if ( memory ) {
					list = [];

				// Otherwise, this object is spent
				} else {
					list = "";
				}
			}
		},

		// Actual Callbacks object
		self = {

			// Add a callback or a collection of callbacks to the list
			add: function() {
				if ( list ) {

					// If we have memory from a past run, we should fire after adding
					if ( memory && !firing ) {
						firingIndex = list.length - 1;
						queue.push( memory );
					}

					( function add( args ) {
						jQuery.each( args, function( _, arg ) {
							if ( isFunction( arg ) ) {
								if ( !options.unique || !self.has( arg ) ) {
									list.push( arg );
								}
							} else if ( arg && arg.length && toType( arg ) !== "string" ) {

								// Inspect recursively
								add( arg );
							}
						} );
					} )( arguments );

					if ( memory && !firing ) {
						fire();
					}
				}
				return this;
			},

			// Remove a callback from the list
			remove: function() {
				jQuery.each( arguments, function( _, arg ) {
					var index;
					while ( ( index = jQuery.inArray( arg, list, index ) ) > -1 ) {
						list.splice( index, 1 );

						// Handle firing indexes
						if ( index <= firingIndex ) {
							firingIndex--;
						}
					}
				} );
				return this;
			},

			// Check if a given callback is in the list.
			// If no argument is given, return whether or not list has callbacks attached.
			has: function( fn ) {
				return fn ?
					jQuery.inArray( fn, list ) > -1 :
					list.length > 0;
			},

			// Remove all callbacks from the list
			empty: function() {
				if ( list ) {
					list = [];
				}
				return this;
			},

			// Disable .fire and .add
			// Abort any current/pending executions
			// Clear all callbacks and values
			disable: function() {
				locked = queue = [];
				list = memory = "";
				return this;
			},
			disabled: function() {
				return !list;
			},

			// Disable .fire
			// Also disable .add unless we have memory (since it would have no effect)
			// Abort any pending executions
			lock: function() {
				locked = queue = [];
				if ( !memory && !firing ) {
					list = memory = "";
				}
				return this;
			},
			locked: function() {
				return !!locked;
			},

			// Call all callbacks with the given context and arguments
			fireWith: function( context, args ) {
				if ( !locked ) {
					args = args || [];
					args = [ context, args.slice ? args.slice() : args ];
					queue.push( args );
					if ( !firing ) {
						fire();
					}
				}
				return this;
			},

			// Call all the callbacks with the given arguments
			fire: function() {
				self.fireWith( this, arguments );
				return this;
			},

			// To know if the callbacks have already been called at least once
			fired: function() {
				return !!fired;
			}
		};

	return self;
};


function Identity( v ) {
	return v;
}
function Thrower( ex ) {
	throw ex;
}

function adoptValue( value, resolve, reject, noValue ) {
	var method;

	try {

		// Check for promise aspect first to privilege synchronous behavior
		if ( value && isFunction( ( method = value.promise ) ) ) {
			method.call( value ).done( resolve ).fail( reject );

		// Other thenables
		} else if ( value && isFunction( ( method = value.then ) ) ) {
			method.call( value, resolve, reject );

		// Other non-thenables
		} else {

			// Control `resolve` arguments by letting Array#slice cast boolean `noValue` to integer:
			// * false: [ value ].slice( 0 ) => resolve( value )
			// * true: [ value ].slice( 1 ) => resolve()
			resolve.apply( undefined, [ value ].slice( noValue ) );
		}

	// For Promises/A+, convert exceptions into rejections
	// Since jQuery.when doesn't unwrap thenables, we can skip the extra checks appearing in
	// Deferred#then to conditionally suppress rejection.
	} catch ( value ) {

		// Support: Android 4.0 only
		// Strict mode functions invoked without .call/.apply get global-object context
		reject.apply( undefined, [ value ] );
	}
}

jQuery.extend( {

	Deferred: function( func ) {
		var tuples = [

				// action, add listener, callbacks,
				// ... .then handlers, argument index, [final state]
				[ "notify", "progress", jQuery.Callbacks( "memory" ),
					jQuery.Callbacks( "memory" ), 2 ],
				[ "resolve", "done", jQuery.Callbacks( "once memory" ),
					jQuery.Callbacks( "once memory" ), 0, "resolved" ],
				[ "reject", "fail", jQuery.Callbacks( "once memory" ),
					jQuery.Callbacks( "once memory" ), 1, "rejected" ]
			],
			state = "pending",
			promise = {
				state: function() {
					return state;
				},
				always: function() {
					deferred.done( arguments ).fail( arguments );
					return this;
				},
				"catch": function( fn ) {
					return promise.then( null, fn );
				},

				// Keep pipe for back-compat
				pipe: function( /* fnDone, fnFail, fnProgress */ ) {
					var fns = arguments;

					return jQuery.Deferred( function( newDefer ) {
						jQuery.each( tuples, function( _i, tuple ) {

							// Map tuples (progress, done, fail) to arguments (done, fail, progress)
							var fn = isFunction( fns[ tuple[ 4 ] ] ) && fns[ tuple[ 4 ] ];

							// deferred.progress(function() { bind to newDefer or newDefer.notify })
							// deferred.done(function() { bind to newDefer or newDefer.resolve })
							// deferred.fail(function() { bind to newDefer or newDefer.reject })
							deferred[ tuple[ 1 ] ]( function() {
								var returned = fn && fn.apply( this, arguments );
								if ( returned && isFunction( returned.promise ) ) {
									returned.promise()
										.progress( newDefer.notify )
										.done( newDefer.resolve )
										.fail( newDefer.reject );
								} else {
									newDefer[ tuple[ 0 ] + "With" ](
										this,
										fn ? [ returned ] : arguments
									);
								}
							} );
						} );
						fns = null;
					} ).promise();
				},
				then: function( onFulfilled, onRejected, onProgress ) {
					var maxDepth = 0;
					function resolve( depth, deferred, handler, special ) {
						return function() {
							var that = this,
								args = arguments,
								mightThrow = function() {
									var returned, then;

									// Support: Promises/A+ section 2.3.3.3.3
									// https://promisesaplus.com/#point-59
									// Ignore double-resolution attempts
									if ( depth < maxDepth ) {
										return;
									}

									returned = handler.apply( that, args );

									// Support: Promises/A+ section 2.3.1
									// https://promisesaplus.com/#point-48
									if ( returned === deferred.promise() ) {
										throw new TypeError( "Thenable self-resolution" );
									}

									// Support: Promises/A+ sections 2.3.3.1, 3.5
									// https://promisesaplus.com/#point-54
									// https://promisesaplus.com/#point-75
									// Retrieve `then` only once
									then = returned &&

										// Support: Promises/A+ section 2.3.4
										// https://promisesaplus.com/#point-64
										// Only check objects and functions for thenability
										( typeof returned === "object" ||
											typeof returned === "function" ) &&
										returned.then;

									// Handle a returned thenable
									if ( isFunction( then ) ) {

										// Special processors (notify) just wait for resolution
										if ( special ) {
											then.call(
												returned,
												resolve( maxDepth, deferred, Identity, special ),
												resolve( maxDepth, deferred, Thrower, special )
											);

										// Normal processors (resolve) also hook into progress
										} else {

											// ...and disregard older resolution values
											maxDepth++;

											then.call(
												returned,
												resolve( maxDepth, deferred, Identity, special ),
												resolve( maxDepth, deferred, Thrower, special ),
												resolve( maxDepth, deferred, Identity,
													deferred.notifyWith )
											);
										}

									// Handle all other returned values
									} else {

										// Only substitute handlers pass on context
										// and multiple values (non-spec behavior)
										if ( handler !== Identity ) {
											that = undefined;
											args = [ returned ];
										}

										// Process the value(s)
										// Default process is resolve
										( special || deferred.resolveWith )( that, args );
									}
								},

								// Only normal processors (resolve) catch and reject exceptions
								process = special ?
									mightThrow :
									function() {
										try {
											mightThrow();
										} catch ( e ) {

											if ( jQuery.Deferred.exceptionHook ) {
												jQuery.Deferred.exceptionHook( e,
													process.error );
											}

											// Support: Promises/A+ section 2.3.3.3.4.1
											// https://promisesaplus.com/#point-61
											// Ignore post-resolution exceptions
											if ( depth + 1 >= maxDepth ) {

												// Only substitute handlers pass on context
												// and multiple values (non-spec behavior)
												if ( handler !== Thrower ) {
													that = undefined;
													args = [ e ];
												}

												deferred.rejectWith( that, args );
											}
										}
									};

							// Support: Promises/A+ section 2.3.3.3.1
							// https://promisesaplus.com/#point-57
							// Re-resolve promises immediately to dodge false rejection from
							// subsequent errors
							if ( depth ) {
								process();
							} else {

								// Call an optional hook to record the error, in case of exception
								// since it's otherwise lost when execution goes async
								if ( jQuery.Deferred.getErrorHook ) {
									process.error = jQuery.Deferred.getErrorHook();

								// The deprecated alias of the above. While the name suggests
								// returning the stack, not an error instance, jQuery just passes
								// it directly to `console.warn` so both will work; an instance
								// just better cooperates with source maps.
								} else if ( jQuery.Deferred.getStackHook ) {
									process.error = jQuery.Deferred.getStackHook();
								}
								window.setTimeout( process );
							}
						};
					}

					return jQuery.Deferred( function( newDefer ) {

						// progress_handlers.add( ... )
						tuples[ 0 ][ 3 ].add(
							resolve(
								0,
								newDefer,
								isFunction( onProgress ) ?
									onProgress :
									Identity,
								newDefer.notifyWith
							)
						);

						// fulfilled_handlers.add( ... )
						tuples[ 1 ][ 3 ].add(
							resolve(
								0,
								newDefer,
								isFunction( onFulfilled ) ?
									onFulfilled :
									Identity
							)
						);

						// rejected_handlers.add( ... )
						tuples[ 2 ][ 3 ].add(
							resolve(
								0,
								newDefer,
								isFunction( onRejected ) ?
									onRejected :
									Thrower
							)
						);
					} ).promise();
				},

				// Get a promise for this deferred
				// If obj is provided, the promise aspect is added to the object
				promise: function( obj ) {
					return obj != null ? jQuery.extend( obj, promise ) : promise;
				}
			},
			deferred = {};

		// Add list-specific methods
		jQuery.each( tuples, function( i, tuple ) {
			var list = tuple[ 2 ],
				stateString = tuple[ 5 ];

			// promise.progress = list.add
			// promise.done = list.add
			// promise.fail = list.add
			promise[ tuple[ 1 ] ] = list.add;

			// Handle state
			if ( stateString ) {
				list.add(
					function() {

						// state = "resolved" (i.e., fulfilled)
						// state = "rejected"
						state = stateString;
					},

					// rejected_callbacks.disable
					// fulfilled_callbacks.disable
					tuples[ 3 - i ][ 2 ].disable,

					// rejected_handlers.disable
					// fulfilled_handlers.disable
					tuples[ 3 - i ][ 3 ].disable,

					// progress_callbacks.lock
					tuples[ 0 ][ 2 ].lock,

					// progress_handlers.lock
					tuples[ 0 ][ 3 ].lock
				);
			}

			// progress_handlers.fire
			// fulfilled_handlers.fire
			// rejected_handlers.fire
			list.add( tuple[ 3 ].fire );

			// deferred.notify = function() { deferred.notifyWith(...) }
			// deferred.resolve = function() { deferred.resolveWith(...) }
			// deferred.reject = function() { deferred.rejectWith(...) }
			deferred[ tuple[ 0 ] ] = function() {
				deferred[ tuple[ 0 ] + "With" ]( this === deferred ? undefined : this, arguments );
				return this;
			};

			// deferred.notifyWith = list.fireWith
			// deferred.resolveWith = list.fireWith
			// deferred.rejectWith = list.fireWith
			deferred[ tuple[ 0 ] + "With" ] = list.fireWith;
		} );

		// Make the deferred a promise
		promise.promise( deferred );

		// Call given func if any
		if ( func ) {
			func.call( deferred, deferred );
		}

		// All done!
		return deferred;
	},

	// Deferred helper
	when: function( singleValue ) {
		var

			// count of uncompleted subordinates
			remaining = arguments.length,

			// count of unprocessed arguments
			i = remaining,

			// subordinate fulfillment data
			resolveContexts = Array( i ),
			resolveValues = slice.call( arguments ),

			// the primary Deferred
			primary = jQuery.Deferred(),

			// subordinate callback factory
			updateFunc = function( i ) {
				return function( value ) {
					resolveContexts[ i ] = this;
					resolveValues[ i ] = arguments.length > 1 ? slice.call( arguments ) : value;
					if ( !( --remaining ) ) {
						primary.resolveWith( resolveContexts, resolveValues );
					}
				};
			};

		// Single- and empty arguments are adopted like Promise.resolve
		if ( remaining <= 1 ) {
			adoptValue( singleValue, primary.done( updateFunc( i ) ).resolve, primary.reject,
				!remaining );

			// Use .then() to unwrap secondary thenables (cf. gh-3000)
			if ( primary.state() === "pending" ||
				isFunction( resolveValues[ i ] && resolveValues[ i ].then ) ) {

				return primary.then();
			}
		}

		// Multiple arguments are aggregated like Promise.all array elements
		while ( i-- ) {
			adoptValue( resolveValues[ i ], updateFunc( i ), primary.reject );
		}

		return primary.promise();
	}
} );


// These usually indicate a programmer mistake during development,
// warn about them ASAP rather than swallowing them by default.
var rerrorNames = /^(Eval|Internal|Range|Reference|Syntax|Type|URI)Error$/;

// If `jQuery.Deferred.getErrorHook` is defined, `asyncError` is an error
// captured before the async barrier to get the original error cause
// which may otherwise be hidden.
jQuery.Deferred.exceptionHook = function( error, asyncError ) {

	// Support: IE 8 - 9 only
	// Console exists when dev tools are open, which can happen at any time
	if ( window.console && window.console.warn && error && rerrorNames.test( error.name ) ) {
		window.console.warn( "jQuery.Deferred exception: " + error.message,
			error.stack, asyncError );
	}
};




jQuery.readyException = function( error ) {
	window.setTimeout( function() {
		throw error;
	} );
};




// The deferred used on DOM ready
var readyList = jQuery.Deferred();

jQuery.fn.ready = function( fn ) {

	readyList
		.then( fn )

		// Wrap jQuery.readyException in a function so that the lookup
		// happens at the time of error handling instead of callback
		// registration.
		.catch( function( error ) {
			jQuery.readyException( error );
		} );

	return this;
};

jQuery.extend( {

	// Is the DOM ready to be used? Set to true once it occurs.
	isReady: false,

	// A counter to track how many items to wait for before
	// the ready event fires. See trac-6781
	readyWait: 1,

	// Handle when the DOM is ready
	ready: function( wait ) {

		// Abort if there are pending holds or we're already ready
		if ( wait === true ? --jQuery.readyWait : jQuery.isReady ) {
			return;
		}

		// Remember that the DOM is ready
		jQuery.isReady = true;

		// If a normal DOM Ready event fired, decrement, and wait if need be
		if ( wait !== true && --jQuery.readyWait > 0 ) {
			return;
		}

		// If there are functions bound, to execute
		readyList.resolveWith( document, [ jQuery ] );
	}
} );

jQuery.ready.then = readyList.then;

// The ready event handler and self cleanup method
function completed() {
	document.removeEventListener( "DOMContentLoaded", completed );
	window.removeEventListener( "load", completed );
	jQuery.ready();
}

// Catch cases where $(document).ready() is called
// after the browser event has already occurred.
// Support: IE <=9 - 10 only
// Older IE sometimes signals "interactive" too soon
if ( document.readyState === "complete" ||
	( document.readyState !== "loading" && !document.documentElement.doScroll ) ) {

	// Handle it asynchronously to allow scripts the opportunity to delay ready
	window.setTimeout( jQuery.ready );

} else {

	// Use the handy event callback
	document.addEventListener( "DOMContentLoaded", completed );

	// A fallback to window.onload, that will always work
	window.addEventListener( "load", completed );
}




// Multifunctional method to get and set values of a collection
// The value/s can optionally be executed if it's a function
var access = function( elems, fn, key, value, chainable, emptyGet, raw ) {
	var i = 0,
		len = elems.length,
		bulk = key == null;

	// Sets many values
	if ( toType( key ) === "object" ) {
		chainable = true;
		for ( i in key ) {
			access( elems, fn, i, key[ i ], true, emptyGet, raw );
		}

	// Sets one value
	} else if ( value !== undefined ) {
		chainable = true;

		if ( !isFunction( value ) ) {
			raw = true;
		}

		if ( bulk ) {

			// Bulk operations run against the entire set
			if ( raw ) {
				fn.call( elems, value );
				fn = null;

			// ...except when executing function values
			} else {
				bulk = fn;
				fn = function( elem, _key, value ) {
					return bulk.call( jQuery( elem ), value );
				};
			}
		}

		if ( fn ) {
			for ( ; i < len; i++ ) {
				fn(
					elems[ i ], key, raw ?
						value :
						value.call( elems[ i ], i, fn( elems[ i ], key ) )
				);
			}
		}
	}

	if ( chainable ) {
		return elems;
	}

	// Gets
	if ( bulk ) {
		return fn.call( elems );
	}

	return len ? fn( elems[ 0 ], key ) : emptyGet;
};


// Matches dashed string for camelizing
var rmsPrefix = /^-ms-/,
	rdashAlpha = /-([a-z])/g;

// Used by camelCase as callback to replace()
function fcamelCase( _all, letter ) {
	return letter.toUpperCase();
}

// Convert dashed to camelCase; used by the css and data modules
// Support: IE <=9 - 11, Edge 12 - 15
// Microsoft forgot to hump their vendor prefix (trac-9572)
function camelCase( string ) {
	return string.replace( rmsPrefix, "ms-" ).replace( rdashAlpha, fcamelCase );
}
var acceptData = function( owner ) {

	// Accepts only:
	//  - Node
	//    - Node.ELEMENT_NODE
	//    - Node.DOCUMENT_NODE
	//  - Object
	//    - Any
	return owner.nodeType === 1 || owner.nodeType === 9 || !( +owner.nodeType );
};




function Data() {
	this.expando = jQuery.expando + Data.uid++;
}

Data.uid = 1;

Data.prototype = {

	cache: function( owner ) {

		// Check if the owner object already has a cache
		var value = owner[ this.expando ];

		// If not, create one
		if ( !value ) {
			value = {};

			// We can accept data for non-element nodes in modern browsers,
			// but we should not, see trac-8335.
			// Always return an empty object.
			if ( acceptData( owner ) ) {

				// If it is a node unlikely to be stringify-ed or looped over
				// use plain assignment
				if ( owner.nodeType ) {
					owner[ this.expando ] = value;

				// Otherwise secure it in a non-enumerable property
				// configurable must be true to allow the property to be
				// deleted when data is removed
				} else {
					Object.defineProperty( owner, this.expando, {
						value: value,
						configurable: true
					} );
				}
			}
		}

		return value;
	},
	set: function( owner, data, value ) {
		var prop,
			cache = this.cache( owner );

		// Handle: [ owner, key, value ] args
		// Always use camelCase key (gh-2257)
		if ( typeof data === "string" ) {
			cache[ camelCase( data ) ] = value;

		// Handle: [ owner, { properties } ] args
		} else {

			// Copy the properties one-by-one to the cache object
			for ( prop in data ) {
				cache[ camelCase( prop ) ] = data[ prop ];
			}
		}
		return cache;
	},
	get: function( owner, key ) {
		return key === undefined ?
			this.cache( owner ) :

			// Always use camelCase key (gh-2257)
			owner[ this.expando ] && owner[ this.expando ][ camelCase( key ) ];
	},
	access: function( owner, key, value ) {

		// In cases where either:
		//
		//   1. No key was specified
		//   2. A string key was specified, but no value provided
		//
		// Take the "read" path and allow the get method to determine
		// which value to return, respectively either:
		//
		//   1. The entire cache object
		//   2. The data stored at the key
		//
		if ( key === undefined ||
				( ( key && typeof key === "string" ) && value === undefined ) ) {

			return this.get( owner, key );
		}

		// When the key is not a string, or both a key and value
		// are specified, set or extend (existing objects) with either:
		//
		//   1. An object of properties
		//   2. A key and value
		//
		this.set( owner, key, value );

		// Since the "set" path can have two possible entry points
		// return the expected data based on which path was taken[*]
		return value !== undefined ? value : key;
	},
	remove: function( owner, key ) {
		var i,
			cache = owner[ this.expando ];

		if ( cache === undefined ) {
			return;
		}

		if ( key !== undefined ) {

			// Support array or space separated string of keys
			if ( Array.isArray( key ) ) {

				// If key is an array of keys...
				// We always set camelCase keys, so remove that.
				key = key.map( camelCase );
			} else {
				key = camelCase( key );

				// If a key with the spaces exists, use it.
				// Otherwise, create an array by matching non-whitespace
				key = key in cache ?
					[ key ] :
					( key.match( rnothtmlwhite ) || [] );
			}

			i = key.length;

			while ( i-- ) {
				delete cache[ key[ i ] ];
			}
		}

		// Remove the expando if there's no more data
		if ( key === undefined || jQuery.isEmptyObject( cache ) ) {

			// Support: Chrome <=35 - 45
			// Webkit & Blink performance suffers when deleting properties
			// from DOM nodes, so set to undefined instead
			// https://bugs.chromium.org/p/chromium/issues/detail?id=378607 (bug restricted)
			if ( owner.nodeType ) {
				owner[ this.expando ] = undefined;
			} else {
				delete owner[ this.expando ];
			}
		}
	},
	hasData: function( owner ) {
		var cache = owner[ this.expando ];
		return cache !== undefined && !jQuery.isEmptyObject( cache );
	}
};
var dataPriv = new Data();

var dataUser = new Data();



//	Implementation Summary
//
//	1. Enforce API surface and semantic compatibility with 1.9.x branch
//	2. Improve the module's maintainability by reducing the storage
//		paths to a single mechanism.
//	3. Use the same single mechanism to support "private" and "user" data.
//	4. _Never_ expose "private" data to user code (TODO: Drop _data, _removeData)
//	5. Avoid exposing implementation details on user objects (eg. expando properties)
//	6. Provide a clear path for implementation upgrade to WeakMap in 2014

var rbrace = /^(?:\{[\w\W]*\}|\[[\w\W]*\])$/,
	rmultiDash = /[A-Z]/g;

function getData( data ) {
	if ( data === "true" ) {
		return true;
	}

	if ( data === "false" ) {
		return false;
	}

	if ( data === "null" ) {
		return null;
	}

	// Only convert to a number if it doesn't change the string
	if ( data === +data + "" ) {
		return +data;
	}

	if ( rbrace.test( data ) ) {
		return JSON.parse( data );
	}

	return data;
}

function dataAttr( elem, key, data ) {
	var name;

	// If nothing was found internally, try to fetch any
	// data from the HTML5 data-* attribute
	if ( data === undefined && elem.nodeType === 1 ) {
		name = "data-" + key.replace( rmultiDash, "-$&" ).toLowerCase();
		data = elem.getAttribute( name );

		if ( typeof data === "string" ) {
			try {
				data = getData( data );
			} catch ( e ) {}

			// Make sure we set the data so it isn't changed later
			dataUser.set( elem, key, data );
		} else {
			data = undefined;
		}
	}
	return data;
}

jQuery.extend( {
	hasData: function( elem ) {
		return dataUser.hasData( elem ) || dataPriv.hasData( elem );
	},

	data: function( elem, name, data ) {
		return dataUser.access( elem, name, data );
	},

	removeData: function( elem, name ) {
		dataUser.remove( elem, name );
	},

	// TODO: Now that all calls to _data and _removeData have been replaced
	// with direct calls to dataPriv methods, these can be deprecated.
	_data: function( elem, name, data ) {
		return dataPriv.access( elem, name, data );
	},

	_removeData: function( elem, name ) {
		dataPriv.remove( elem, name );
	}
} );

jQuery.fn.extend( {
	data: function( key, value ) {
		var i, name, data,
			elem = this[ 0 ],
			attrs = elem && elem.attributes;

		// Gets all values
		if ( key === undefined ) {
			if ( this.length ) {
				data = dataUser.get( elem );

				if ( elem.nodeType === 1 && !dataPriv.get( elem, "hasDataAttrs" ) ) {
					i = attrs.length;
					while ( i-- ) {

						// Support: IE 11 only
						// The attrs elements can be null (trac-14894)
						if ( attrs[ i ] ) {
							name = attrs[ i ].name;
							if ( name.indexOf( "data-" ) === 0 ) {
								name = camelCase( name.slice( 5 ) );
								dataAttr( elem, name, data[ name ] );
							}
						}
					}
					dataPriv.set( elem, "hasDataAttrs", true );
				}
			}

			return data;
		}

		// Sets multiple values
		if ( typeof key === "object" ) {
			return this.each( function() {
				dataUser.set( this, key );
			} );
		}

		return access( this, function( value ) {
			var data;

			// The calling jQuery object (element matches) is not empty
			// (and therefore has an element appears at this[ 0 ]) and the
			// `value` parameter was not undefined. An empty jQuery object
			// will result in `undefined` for elem = this[ 0 ] which will
			// throw an exception if an attempt to read a data cache is made.
			if ( elem && value === undefined ) {

				// Attempt to get data from the cache
				// The key will always be camelCased in Data
				data = dataUser.get( elem, key );
				if ( data !== undefined ) {
					return data;
				}

				// Attempt to "discover" the data in
				// HTML5 custom data-* attrs
				data = dataAttr( elem, key );
				if ( data !== undefined ) {
					return data;
				}

				// We tried really hard, but the data doesn't exist.
				return;
			}

			// Set the data...
			this.each( function() {

				// We always store the camelCased key
				dataUser.set( this, key, value );
			} );
		}, null, value, arguments.length > 1, null, true );
	},

	removeData: function( key ) {
		return this.each( function() {
			dataUser.remove( this, key );
		} );
	}
} );


jQuery.extend( {
	queue: function( elem, type, data ) {
		var queue;

		if ( elem ) {
			type = ( type || "fx" ) + "queue";
			queue = dataPriv.get( elem, type );

			// Speed up dequeue by getting out quickly if this is just a lookup
			if ( data ) {
				if ( !queue || Array.isArray( data ) ) {
					queue = dataPriv.access( elem, type, jQuery.makeArray( data ) );
				} else {
					queue.push( data );
				}
			}
			return queue || [];
		}
	},

	dequeue: function( elem, type ) {
		type = type || "fx";

		var queue = jQuery.queue( elem, type ),
			startLength = queue.length,
			fn = queue.shift(),
			hooks = jQuery._queueHooks( elem, type ),
			next = function() {
				jQuery.dequeue( elem, type );
			};

		// If the fx queue is dequeued, always remove the progress sentinel
		if ( fn === "inprogress" ) {
			fn = queue.shift();
			startLength--;
		}

		if ( fn ) {

			// Add a progress sentinel to prevent the fx queue from being
			// automatically dequeued
			if ( type === "fx" ) {
				queue.unshift( "inprogress" );
			}

			// Clear up the last queue stop function
			delete hooks.stop;
			fn.call( elem, next, hooks );
		}

		if ( !startLength && hooks ) {
			hooks.empty.fire();
		}
	},

	// Not public - generate a queueHooks object, or return the current one
	_queueHooks: function( elem, type ) {
		var key = type + "queueHooks";
		return dataPriv.get( elem, key ) || dataPriv.access( elem, key, {
			empty: jQuery.Callbacks( "once memory" ).add( function() {
				dataPriv.remove( elem, [ type + "queue", key ] );
			} )
		} );
	}
} );

jQuery.fn.extend( {
	queue: function( type, data ) {
		var setter = 2;

		if ( typeof type !== "string" ) {
			data = type;
			type = "fx";
			setter--;
		}

		if ( arguments.length < setter ) {
			return jQuery.queue( this[ 0 ], type );
		}

		return data === undefined ?
			this :
			this.each( function() {
				var queue = jQuery.queue( this, type, data );

				// Ensure a hooks for this queue
				jQuery._queueHooks( this, type );

				if ( type === "fx" && queue[ 0 ] !== "inprogress" ) {
					jQuery.dequeue( this, type );
				}
			} );
	},
	dequeue: function( type ) {
		return this.each( function() {
			jQuery.dequeue( this, type );
		} );
	},
	clearQueue: function( type ) {
		return this.queue( type || "fx", [] );
	},

	// Get a promise resolved when queues of a certain type
	// are emptied (fx is the type by default)
	promise: function( type, obj ) {
		var tmp,
			count = 1,
			defer = jQuery.Deferred(),
			elements = this,
			i = this.length,
			resolve = function() {
				if ( !( --count ) ) {
					defer.resolveWith( elements, [ elements ] );
				}
			};

		if ( typeof type !== "string" ) {
			obj = type;
			type = undefined;
		}
		type = type || "fx";

		while ( i-- ) {
			tmp = dataPriv.get( elements[ i ], type + "queueHooks" );
			if ( tmp && tmp.empty ) {
				count++;
				tmp.empty.add( resolve );
			}
		}
		resolve();
		return defer.promise( obj );
	}
} );
var pnum = ( /[+-]?(?:\d*\.|)\d+(?:[eE][+-]?\d+|)/ ).source;

var rcssNum = new RegExp( "^(?:([+-])=|)(" + pnum + ")([a-z%]*)$", "i" );


var cssExpand = [ "Top", "Right", "Bottom", "Left" ];

var documentElement = document.documentElement;



	var isAttached = function( elem ) {
			return jQuery.contains( elem.ownerDocument, elem );
		},
		composed = { composed: true };

	// Support: IE 9 - 11+, Edge 12 - 18+, iOS 10.0 - 10.2 only
	// Check attachment across shadow DOM boundaries when possible (gh-3504)
	// Support: iOS 10.0-10.2 only
	// Early iOS 10 versions support `attachShadow` but not `getRootNode`,
	// leading to errors. We need to check for `getRootNode`.
	if ( documentElement.getRootNode ) {
		isAttached = function( elem ) {
			return jQuery.contains( elem.ownerDocument, elem ) ||
				elem.getRootNode( composed ) === elem.ownerDocument;
		};
	}
var isHiddenWithinTree = function( elem, el ) {

		// isHiddenWithinTree might be called from jQuery#filter function;
		// in that case, element will be second argument
		elem = el || elem;

		// Inline style trumps all
		return elem.style.display === "none" ||
			elem.style.display === "" &&

			// Otherwise, check computed style
			// Support: Firefox <=43 - 45
			// Disconnected elements can have computed display: none, so first confirm that elem is
			// in the document.
			isAttached( elem ) &&

			jQuery.css( elem, "display" ) === "none";
	};



function adjustCSS( elem, prop, valueParts, tween ) {
	var adjusted, scale,
		maxIterations = 20,
		currentValue = tween ?
			function() {
				return tween.cur();
			} :
			function() {
				return jQuery.css( elem, prop, "" );
			},
		initial = currentValue(),
		unit = valueParts && valueParts[ 3 ] || ( jQuery.cssNumber[ prop ] ? "" : "px" ),

		// Starting value computation is required for potential unit mismatches
		initialInUnit = elem.nodeType &&
			( jQuery.cssNumber[ prop ] || unit !== "px" && +initial ) &&
			rcssNum.exec( jQuery.css( elem, prop ) );

	if ( initialInUnit && initialInUnit[ 3 ] !== unit ) {

		// Support: Firefox <=54
		// Halve the iteration target value to prevent interference from CSS upper bounds (gh-2144)
		initial = initial / 2;

		// Trust units reported by jQuery.css
		unit = unit || initialInUnit[ 3 ];

		// Iteratively approximate from a nonzero starting point
		initialInUnit = +initial || 1;

		while ( maxIterations-- ) {

			// Evaluate and update our best guess (doubling guesses that zero out).
			// Finish if the scale equals or crosses 1 (making the old*new product non-positive).
			jQuery.style( elem, prop, initialInUnit + unit );
			if ( ( 1 - scale ) * ( 1 - ( scale = currentValue() / initial || 0.5 ) ) <= 0 ) {
				maxIterations = 0;
			}
			initialInUnit = initialInUnit / scale;

		}

		initialInUnit = initialInUnit * 2;
		jQuery.style( elem, prop, initialInUnit + unit );

		// Make sure we update the tween properties later on
		valueParts = valueParts || [];
	}

	if ( valueParts ) {
		initialInUnit = +initialInUnit || +initial || 0;

		// Apply relative offset (+=/-=) if specified
		adjusted = valueParts[ 1 ] ?
			initialInUnit + ( valueParts[ 1 ] + 1 ) * valueParts[ 2 ] :
			+valueParts[ 2 ];
		if ( tween ) {
			tween.unit = unit;
			tween.start = initialInUnit;
			tween.end = adjusted;
		}
	}
	return adjusted;
}


var defaultDisplayMap = {};

function getDefaultDisplay( elem ) {
	var temp,
		doc = elem.ownerDocument,
		nodeName = elem.nodeName,
		display = defaultDisplayMap[ nodeName ];

	if ( display ) {
		return display;
	}

	temp = doc.body.appendChild( doc.createElement( nodeName ) );
	display = jQuery.css( temp, "display" );

	temp.parentNode.removeChild( temp );

	if ( display === "none" ) {
		display = "block";
	}
	defaultDisplayMap[ nodeName ] = display;

	return display;
}

function showHide( elements, show ) {
	var display, elem,
		values = [],
		index = 0,
		length = elements.length;

	// Determine new display value for elements that need to change
	for ( ; index < length; index++ ) {
		elem = elements[ index ];
		if ( !elem.style ) {
			continue;
		}

		display = elem.style.display;
		if ( show ) {

			// Since we force visibility upon cascade-hidden elements, an immediate (and slow)
			// check is required in this first loop unless we have a nonempty display value (either
			// inline or about-to-be-restored)
			if ( display === "none" ) {
				values[ index ] = dataPriv.get( elem, "display" ) || null;
				if ( !values[ index ] ) {
					elem.style.display = "";
				}
			}
			if ( elem.style.display === "" && isHiddenWithinTree( elem ) ) {
				values[ index ] = getDefaultDisplay( elem );
			}
		} else {
			if ( display !== "none" ) {
				values[ index ] = "none";

				// Remember what we're overwriting
				dataPriv.set( elem, "display", display );
			}
		}
	}

	// Set the display of the elements in a second loop to avoid constant reflow
	for ( index = 0; index < length; index++ ) {
		if ( values[ index ] != null ) {
			elements[ index ].style.display = values[ index ];
		}
	}

	return elements;
}

jQuery.fn.extend( {
	show: function() {
		return showHide( this, true );
	},
	hide: function() {
		return showHide( this );
	},
	toggle: function( state ) {
		if ( typeof state === "boolean" ) {
			return state ? this.show() : this.hide();
		}

		return this.each( function() {
			if ( isHiddenWithinTree( this ) ) {
				jQuery( this ).show();
			} else {
				jQuery( this ).hide();
			}
		} );
	}
} );
var rcheckableType = ( /^(?:checkbox|radio)$/i );

var rtagName = ( /<([a-z][^\/\0>\x20\t\r\n\f]*)/i );

var rscriptType = ( /^$|^module$|\/(?:java|ecma)script/i );



( function() {
	var fragment = document.createDocumentFragment(),
		div = fragment.appendChild( document.createElement( "div" ) ),
		input = document.createElement( "input" );

	// Support: Android 4.0 - 4.3 only
	// Check state lost if the name is set (trac-11217)
	// Support: Windows Web Apps (WWA)
	// `name` and `type` must use .setAttribute for WWA (trac-14901)
	input.setAttribute( "type", "radio" );
	input.setAttribute( "checked", "checked" );
	input.setAttribute( "name", "t" );

	div.appendChild( input );

	// Support: Android <=4.1 only
	// Older WebKit doesn't clone checked state correctly in fragments
	support.checkClone = div.cloneNode( true ).cloneNode( true ).lastChild.checked;

	// Support: IE <=11 only
	// Make sure textarea (and checkbox) defaultValue is properly cloned
	div.innerHTML = "<textarea>x</textarea>";
	support.noCloneChecked = !!div.cloneNode( true ).lastChild.defaultValue;

	// Support: IE <=9 only
	// IE <=9 replaces <option> tags with their contents when inserted outside of
	// the select element.
	div.innerHTML = "<option></option>";
	support.option = !!div.lastChild;
} )();


// We have to close these tags to support XHTML (trac-13200)
var wrapMap = {

	// XHTML parsers do not magically insert elements in the
	// same way that tag soup parsers do. So we cannot shorten
	// this by omitting <tbody> or other required elements.
	thead: [ 1, "<table>", "</table>" ],
	col: [ 2, "<table><colgroup>", "</colgroup></table>" ],
	tr: [ 2, "<table><tbody>", "</tbody></table>" ],
	td: [ 3, "<table><tbody><tr>", "</tr></tbody></table>" ],

	_default: [ 0, "", "" ]
};

wrapMap.tbody = wrapMap.tfoot = wrapMap.colgroup = wrapMap.caption = wrapMap.thead;
wrapMap.th = wrapMap.td;

// Support: IE <=9 only
if ( !support.option ) {
	wrapMap.optgroup = wrapMap.option = [ 1, "<select multiple='multiple'>", "</select>" ];
}


function getAll( context, tag ) {

	// Support: IE <=9 - 11 only
	// Use typeof to avoid zero-argument method invocation on host objects (trac-15151)
	var ret;

	if ( typeof context.getElementsByTagName !== "undefined" ) {
		ret = context.getElementsByTagName( tag || "*" );

	} else if ( typeof context.querySelectorAll !== "undefined" ) {
		ret = context.querySelectorAll( tag || "*" );

	} else {
		ret = [];
	}

	if ( tag === undefined || tag && nodeName( context, tag ) ) {
		return jQuery.merge( [ context ], ret );
	}

	return ret;
}


// Mark scripts as having already been evaluated
function setGlobalEval( elems, refElements ) {
	var i = 0,
		l = elems.length;

	for ( ; i < l; i++ ) {
		dataPriv.set(
			elems[ i ],
			"globalEval",
			!refElements || dataPriv.get( refElements[ i ], "globalEval" )
		);
	}
}


var rhtml = /<|&#?\w+;/;

function buildFragment( elems, context, scripts, selection, ignored ) {
	var elem, tmp, tag, wrap, attached, j,
		fragment = context.createDocumentFragment(),
		nodes = [],
		i = 0,
		l = elems.length;

	for ( ; i < l; i++ ) {
		elem = elems[ i ];

		if ( elem || elem === 0 ) {

			// Add nodes directly
			if ( toType( elem ) === "object" ) {

				// Support: Android <=4.0 only, PhantomJS 1 only
				// push.apply(_, arraylike) throws on ancient WebKit
				jQuery.merge( nodes, elem.nodeType ? [ elem ] : elem );

			// Convert non-html into a text node
			} else if ( !rhtml.test( elem ) ) {
				nodes.push( context.createTextNode( elem ) );

			// Convert html into DOM nodes
			} else {
				tmp = tmp || fragment.appendChild( context.createElement( "div" ) );

				// Deserialize a standard representation
				tag = ( rtagName.exec( elem ) || [ "", "" ] )[ 1 ].toLowerCase();
				wrap = wrapMap[ tag ] || wrapMap._default;
				tmp.innerHTML = wrap[ 1 ] + jQuery.htmlPrefilter( elem ) + wrap[ 2 ];

				// Descend through wrappers to the right content
				j = wrap[ 0 ];
				while ( j-- ) {
					tmp = tmp.lastChild;
				}

				// Support: Android <=4.0 only, PhantomJS 1 only
				// push.apply(_, arraylike) throws on ancient WebKit
				jQuery.merge( nodes, tmp.childNodes );

				// Remember the top-level container
				tmp = fragment.firstChild;

				// Ensure the created nodes are orphaned (trac-12392)
				tmp.textContent = "";
			}
		}
	}

	// Remove wrapper from fragment
	fragment.textContent = "";

	i = 0;
	while ( ( elem = nodes[ i++ ] ) ) {

		// Skip elements already in the context collection (trac-4087)
		if ( selection && jQuery.inArray( elem, selection ) > -1 ) {
			if ( ignored ) {
				ignored.push( elem );
			}
			continue;
		}

		attached = isAttached( elem );

		// Append to fragment
		tmp = getAll( fragment.appendChild( elem ), "script" );

		// Preserve script evaluation history
		if ( attached ) {
			setGlobalEval( tmp );
		}

		// Capture executables
		if ( scripts ) {
			j = 0;
			while ( ( elem = tmp[ j++ ] ) ) {
				if ( rscriptType.test( elem.type || "" ) ) {
					scripts.push( elem );
				}
			}
		}
	}

	return fragment;
}


var rtypenamespace = /^([^.]*)(?:\.(.+)|)/;

function returnTrue() {
	return true;
}

function returnFalse() {
	return false;
}

function on( elem, types, selector, data, fn, one ) {
	var origFn, type;

	// Types can be a map of types/handlers
	if ( typeof types === "object" ) {

		// ( types-Object, selector, data )
		if ( typeof selector !== "string" ) {

			// ( types-Object, data )
			data = data || selector;
			selector = undefined;
		}
		for ( type in types ) {
			on( elem, type, selector, data, types[ type ], one );
		}
		return elem;
	}

	if ( data == null && fn == null ) {

		// ( types, fn )
		fn = selector;
		data = selector = undefined;
	} else if ( fn == null ) {
		if ( typeof selector === "string" ) {

			// ( types, selector, fn )
			fn = data;
			data = undefined;
		} else {

			// ( types, data, fn )
			fn = data;
			data = selector;
			selector = undefined;
		}
	}
	if ( fn === false ) {
		fn = returnFalse;
	} else if ( !fn ) {
		return elem;
	}

	if ( one === 1 ) {
		origFn = fn;
		fn = function( event ) {

			// Can use an empty set, since event contains the info
			jQuery().off( event );
			return origFn.apply( this, arguments );
		};

		// Use same guid so caller can remove using origFn
		fn.guid = origFn.guid || ( origFn.guid = jQuery.guid++ );
	}
	return elem.each( function() {
		jQuery.event.add( this, types, fn, data, selector );
	} );
}

/*
 * Helper functions for managing events -- not part of the public interface.
 * Props to Dean Edwards' addEvent library for many of the ideas.
 */
jQuery.event = {

	global: {},

	add: function( elem, types, handler, data, selector ) {

		var handleObjIn, eventHandle, tmp,
			events, t, handleObj,
			special, handlers, type, namespaces, origType,
			elemData = dataPriv.get( elem );

		// Only attach events to objects that accept data
		if ( !acceptData( elem ) ) {
			return;
		}

		// Caller can pass in an object of custom data in lieu of the handler
		if ( handler.handler ) {
			handleObjIn = handler;
			handler = handleObjIn.handler;
			selector = handleObjIn.selector;
		}

		// Ensure that invalid selectors throw exceptions at attach time
		// Evaluate against documentElement in case elem is a non-element node (e.g., document)
		if ( selector ) {
			jQuery.find.matchesSelector( documentElement, selector );
		}

		// Make sure that the handler has a unique ID, used to find/remove it later
		if ( !handler.guid ) {
			handler.guid = jQuery.guid++;
		}

		// Init the element's event structure and main handler, if this is the first
		if ( !( events = elemData.events ) ) {
			events = elemData.events = Object.create( null );
		}
		if ( !( eventHandle = elemData.handle ) ) {
			eventHandle = elemData.handle = function( e ) {

				// Discard the second event of a jQuery.event.trigger() and
				// when an event is called after a page has unloaded
				return typeof jQuery !== "undefined" && jQuery.event.triggered !== e.type ?
					jQuery.event.dispatch.apply( elem, arguments ) : undefined;
			};
		}

		// Handle multiple events separated by a space
		types = ( types || "" ).match( rnothtmlwhite ) || [ "" ];
		t = types.length;
		while ( t-- ) {
			tmp = rtypenamespace.exec( types[ t ] ) || [];
			type = origType = tmp[ 1 ];
			namespaces = ( tmp[ 2 ] || "" ).split( "." ).sort();

			// There *must* be a type, no attaching namespace-only handlers
			if ( !type ) {
				continue;
			}

			// If event changes its type, use the special event handlers for the changed type
			special = jQuery.event.special[ type ] || {};

			// If selector defined, determine special event api type, otherwise given type
			type = ( selector ? special.delegateType : special.bindType ) || type;

			// Update special based on newly reset type
			special = jQuery.event.special[ type ] || {};

			// handleObj is passed to all event handlers
			handleObj = jQuery.extend( {
				type: type,
				origType: origType,
				data: data,
				handler: handler,
				guid: handler.guid,
				selector: selector,
				needsContext: selector && jQuery.expr.match.needsContext.test( selector ),
				namespace: namespaces.join( "." )
			}, handleObjIn );

			// Init the event handler queue if we're the first
			if ( !( handlers = events[ type ] ) ) {
				handlers = events[ type ] = [];
				handlers.delegateCount = 0;

				// Only use addEventListener if the special events handler returns false
				if ( !special.setup ||
					special.setup.call( elem, data, namespaces, eventHandle ) === false ) {

					if ( elem.addEventListener ) {
						elem.addEventListener( type, eventHandle );
					}
				}
			}

			if ( special.add ) {
				special.add.call( elem, handleObj );

				if ( !handleObj.handler.guid ) {
					handleObj.handler.guid = handler.guid;
				}
			}

			// Add to the element's handler list, delegates in front
			if ( selector ) {
				handlers.splice( handlers.delegateCount++, 0, handleObj );
			} else {
				handlers.push( handleObj );
			}

			// Keep track of which events have ever been used, for event optimization
			jQuery.event.global[ type ] = true;
		}

	},

	// Detach an event or set of events from an element
	remove: function( elem, types, handler, selector, mappedTypes ) {

		var j, origCount, tmp,
			events, t, handleObj,
			special, handlers, type, namespaces, origType,
			elemData = dataPriv.hasData( elem ) && dataPriv.get( elem );

		if ( !elemData || !( events = elemData.events ) ) {
			return;
		}

		// Once for each type.namespace in types; type may be omitted
		types = ( types || "" ).match( rnothtmlwhite ) || [ "" ];
		t = types.length;
		while ( t-- ) {
			tmp = rtypenamespace.exec( types[ t ] ) || [];
			type = origType = tmp[ 1 ];
			namespaces = ( tmp[ 2 ] || "" ).split( "." ).sort();

			// Unbind all events (on this namespace, if provided) for the element
			if ( !type ) {
				for ( type in events ) {
					jQuery.event.remove( elem, type + types[ t ], handler, selector, true );
				}
				continue;
			}

			special = jQuery.event.special[ type ] || {};
			type = ( selector ? special.delegateType : special.bindType ) || type;
			handlers = events[ type ] || [];
			tmp = tmp[ 2 ] &&
				new RegExp( "(^|\\.)" + namespaces.join( "\\.(?:.*\\.|)" ) + "(\\.|$)" );

			// Remove matching events
			origCount = j = handlers.length;
			while ( j-- ) {
				handleObj = handlers[ j ];

				if ( ( mappedTypes || origType === handleObj.origType ) &&
					( !handler || handler.guid === handleObj.guid ) &&
					( !tmp || tmp.test( handleObj.namespace ) ) &&
					( !selector || selector === handleObj.selector ||
						selector === "**" && handleObj.selector ) ) {
					handlers.splice( j, 1 );

					if ( handleObj.selector ) {
						handlers.delegateCount--;
					}
					if ( special.remove ) {
						special.remove.call( elem, handleObj );
					}
				}
			}

			// Remove generic event handler if we removed something and no more handlers exist
			// (avoids potential for endless recursion during removal of special event handlers)
			if ( origCount && !handlers.length ) {
				if ( !special.teardown ||
					special.teardown.call( elem, namespaces, elemData.handle ) === false ) {

					jQuery.removeEvent( elem, type, elemData.handle );
				}

				delete events[ type ];
			}
		}

		// Remove data and the expando if it's no longer used
		if ( jQuery.isEmptyObject( events ) ) {
			dataPriv.remove( elem, "handle events" );
		}
	},

	dispatch: function( nativeEvent ) {

		var i, j, ret, matched, handleObj, handlerQueue,
			args = new Array( arguments.length ),

			// Make a writable jQuery.Event from the native event object
			event = jQuery.event.fix( nativeEvent ),

			handlers = (
				dataPriv.get( this, "events" ) || Object.create( null )
			)[ event.type ] || [],
			special = jQuery.event.special[ event.type ] || {};

		// Use the fix-ed jQuery.Event rather than the (read-only) native event
		args[ 0 ] = event;

		for ( i = 1; i < arguments.length; i++ ) {
			args[ i ] = arguments[ i ];
		}

		event.delegateTarget = this;

		// Call the preDispatch hook for the mapped type, and let it bail if desired
		if ( special.preDispatch && special.preDispatch.call( this, event ) === false ) {
			return;
		}

		// Determine handlers
		handlerQueue = jQuery.event.handlers.call( this, event, handlers );

		// Run delegates first; they may want to stop propagation beneath us
		i = 0;
		while ( ( matched = handlerQueue[ i++ ] ) && !event.isPropagationStopped() ) {
			event.currentTarget = matched.elem;

			j = 0;
			while ( ( handleObj = matched.handlers[ j++ ] ) &&
				!event.isImmediatePropagationStopped() ) {

				// If the event is namespaced, then each handler is only invoked if it is
				// specially universal or its namespaces are a superset of the event's.
				if ( !event.rnamespace || handleObj.namespace === false ||
					event.rnamespace.test( handleObj.namespace ) ) {

					event.handleObj = handleObj;
					event.data = handleObj.data;

					ret = ( ( jQuery.event.special[ handleObj.origType ] || {} ).handle ||
						handleObj.handler ).apply( matched.elem, args );

					if ( ret !== undefined ) {
						if ( ( event.result = ret ) === false ) {
							event.preventDefault();
							event.stopPropagation();
						}
					}
				}
			}
		}

		// Call the postDispatch hook for the mapped type
		if ( special.postDispatch ) {
			special.postDispatch.call( this, event );
		}

		return event.result;
	},

	handlers: function( event, handlers ) {
		var i, handleObj, sel, matchedHandlers, matchedSelectors,
			handlerQueue = [],
			delegateCount = handlers.delegateCount,
			cur = event.target;

		// Find delegate handlers
		if ( delegateCount &&

			// Support: IE <=9
			// Black-hole SVG <use> instance trees (trac-13180)
			cur.nodeType &&

			// Support: Firefox <=42
			// Suppress spec-violating clicks indicating a non-primary pointer button (trac-3861)
			// https://www.w3.org/TR/DOM-Level-3-Events/#event-type-click
			// Support: IE 11 only
			// ...but not arrow key "clicks" of radio inputs, which can have `button` -1 (gh-2343)
			!( event.type === "click" && event.button >= 1 ) ) {

			for ( ; cur !== this; cur = cur.parentNode || this ) {

				// Don't check non-elements (trac-13208)
				// Don't process clicks on disabled elements (trac-6911, trac-8165, trac-11382, trac-11764)
				if ( cur.nodeType === 1 && !( event.type === "click" && cur.disabled === true ) ) {
					matchedHandlers = [];
					matchedSelectors = {};
					for ( i = 0; i < delegateCount; i++ ) {
						handleObj = handlers[ i ];

						// Don't conflict with Object.prototype properties (trac-13203)
						sel = handleObj.selector + " ";

						if ( matchedSelectors[ sel ] === undefined ) {
							matchedSelectors[ sel ] = handleObj.needsContext ?
								jQuery( sel, this ).index( cur ) > -1 :
								jQuery.find( sel, this, null, [ cur ] ).length;
						}
						if ( matchedSelectors[ sel ] ) {
							matchedHandlers.push( handleObj );
						}
					}
					if ( matchedHandlers.length ) {
						handlerQueue.push( { elem: cur, handlers: matchedHandlers } );
					}
				}
			}
		}

		// Add the remaining (directly-bound) handlers
		cur = this;
		if ( delegateCount < handlers.length ) {
			handlerQueue.push( { elem: cur, handlers: handlers.slice( delegateCount ) } );
		}

		return handlerQueue;
	},

	addProp: function( name, hook ) {
		Object.defineProperty( jQuery.Event.prototype, name, {
			enumerable: true,
			configurable: true,

			get: isFunction( hook ) ?
				function() {
					if ( this.originalEvent ) {
						return hook( this.originalEvent );
					}
				} :
				function() {
					if ( this.originalEvent ) {
						return this.originalEvent[ name ];
					}
				},

			set: function( value ) {
				Object.defineProperty( this, name, {
					enumerable: true,
					configurable: true,
					writable: true,
					value: value
				} );
			}
		} );
	},

	fix: function( originalEvent ) {
		return originalEvent[ jQuery.expando ] ?
			originalEvent :
			new jQuery.Event( originalEvent );
	},

	special: {
		load: {

			// Prevent triggered image.load events from bubbling to window.load
			noBubble: true
		},
		click: {

			// Utilize native event to ensure correct state for checkable inputs
			setup: function( data ) {

				// For mutual compressibility with _default, replace `this` access with a local var.
				// `|| data` is dead code meant only to preserve the variable through minification.
				var el = this || data;

				// Claim the first handler
				if ( rcheckableType.test( el.type ) &&
					el.click && nodeName( el, "input" ) ) {

					// dataPriv.set( el, "click", ... )
					leverageNative( el, "click", true );
				}

				// Return false to allow normal processing in the caller
				return false;
			},
			trigger: function( data ) {

				// For mutual compressibility with _default, replace `this` access with a local var.
				// `|| data` is dead code meant only to preserve the variable through minification.
				var el = this || data;

				// Force setup before triggering a click
				if ( rcheckableType.test( el.type ) &&
					el.click && nodeName( el, "input" ) ) {

					leverageNative( el, "click" );
				}

				// Return non-false to allow normal event-path propagation
				return true;
			},

			// For cross-browser consistency, suppress native .click() on links
			// Also prevent it if we're currently inside a leveraged native-event stack
			_default: function( event ) {
				var target = event.target;
				return rcheckableType.test( target.type ) &&
					target.click && nodeName( target, "input" ) &&
					dataPriv.get( target, "click" ) ||
					nodeName( target, "a" );
			}
		},

		beforeunload: {
			postDispatch: function( event ) {

				// Support: Firefox 20+
				// Firefox doesn't alert if the returnValue field is not set.
				if ( event.result !== undefined && event.originalEvent ) {
					event.originalEvent.returnValue = event.result;
				}
			}
		}
	}
};

// Ensure the presence of an event listener that handles manually-triggered
// synthetic events by interrupting progress until reinvoked in response to
// *native* events that it fires directly, ensuring that state changes have
// already occurred before other listeners are invoked.
function leverageNative( el, type, isSetup ) {

	// Missing `isSetup` indicates a trigger call, which must force setup through jQuery.event.add
	if ( !isSetup ) {
		if ( dataPriv.get( el, type ) === undefined ) {
			jQuery.event.add( el, type, returnTrue );
		}
		return;
	}

	// Register the controller as a special universal handler for all event namespaces
	dataPriv.set( el, type, false );
	jQuery.event.add( el, type, {
		namespace: false,
		handler: function( event ) {
			var result,
				saved = dataPriv.get( this, type );

			if ( ( event.isTrigger & 1 ) && this[ type ] ) {

				// Interrupt processing of the outer synthetic .trigger()ed event
				if ( !saved ) {

					// Store arguments for use when handling the inner native event
					// There will always be at least one argument (an event object), so this array
					// will not be confused with a leftover capture object.
					saved = slice.call( arguments );
					dataPriv.set( this, type, saved );

					// Trigger the native event and capture its result
					this[ type ]();
					result = dataPriv.get( this, type );
					dataPriv.set( this, type, false );

					if ( saved !== result ) {

						// Cancel the outer synthetic event
						event.stopImmediatePropagation();
						event.preventDefault();

						return result;
					}

				// If this is an inner synthetic event for an event with a bubbling surrogate
				// (focus or blur), assume that the surrogate already propagated from triggering
				// the native event and prevent that from happening again here.
				// This technically gets the ordering wrong w.r.t. to `.trigger()` (in which the
				// bubbling surrogate propagates *after* the non-bubbling base), but that seems
				// less bad than duplication.
				} else if ( ( jQuery.event.special[ type ] || {} ).delegateType ) {
					event.stopPropagation();
				}

			// If this is a native event triggered above, everything is now in order
			// Fire an inner synthetic event with the original arguments
			} else if ( saved ) {

				// ...and capture the result
				dataPriv.set( this, type, jQuery.event.trigger(
					saved[ 0 ],
					saved.slice( 1 ),
					this
				) );

				// Abort handling of the native event by all jQuery handlers while allowing
				// native handlers on the same element to run. On target, this is achieved
				// by stopping immediate propagation just on the jQuery event. However,
				// the native event is re-wrapped by a jQuery one on each level of the
				// propagation so the only way to stop it for jQuery is to stop it for
				// everyone via native `stopPropagation()`. This is not a problem for
				// focus/blur which don't bubble, but it does also stop click on checkboxes
				// and radios. We accept this limitation.
				event.stopPropagation();
				event.isImmediatePropagationStopped = returnTrue;
			}
		}
	} );
}

jQuery.removeEvent = function( elem, type, handle ) {

	// This "if" is needed for plain objects
	if ( elem.removeEventListener ) {
		elem.removeEventListener( type, handle );
	}
};

jQuery.Event = function( src, props ) {

	// Allow instantiation without the 'new' keyword
	if ( !( this instanceof jQuery.Event ) ) {
		return new jQuery.Event( src, props );
	}

	// Event object
	if ( src && src.type ) {
		this.originalEvent = src;
		this.type = src.type;

		// Events bubbling up the document may have been marked as prevented
		// by a handler lower down the tree; reflect the correct value.
		this.isDefaultPrevented = src.defaultPrevented ||
				src.defaultPrevented === undefined &&

				// Support: Android <=2.3 only
				src.returnValue === false ?
			returnTrue :
			returnFalse;

		// Create target properties
		// Support: Safari <=6 - 7 only
		// Target should not be a text node (trac-504, trac-13143)
		this.target = ( src.target && src.target.nodeType === 3 ) ?
			src.target.parentNode :
			src.target;

		this.currentTarget = src.currentTarget;
		this.relatedTarget = src.relatedTarget;

	// Event type
	} else {
		this.type = src;
	}

	// Put explicitly provided properties onto the event object
	if ( props ) {
		jQuery.extend( this, props );
	}

	// Create a timestamp if incoming event doesn't have one
	this.timeStamp = src && src.timeStamp || Date.now();

	// Mark it as fixed
	this[ jQuery.expando ] = true;
};

// jQuery.Event is based on DOM3 Events as specified by the ECMAScript Language Binding
// https://www.w3.org/TR/2003/WD-DOM-Level-3-Events-20030331/ecma-script-binding.html
jQuery.Event.prototype = {
	constructor: jQuery.Event,
	isDefaultPrevented: returnFalse,
	isPropagationStopped: returnFalse,
	isImmediatePropagationStopped: returnFalse,
	isSimulated: false,

	preventDefault: function() {
		var e = this.originalEvent;

		this.isDefaultPrevented = returnTrue;

		if ( e && !this.isSimulated ) {
			e.preventDefault();
		}
	},
	stopPropagation: function() {
		var e = this.originalEvent;

		this.isPropagationStopped = returnTrue;

		if ( e && !this.isSimulated ) {
			e.stopPropagation();
		}
	},
	stopImmediatePropagation: function() {
		var e = this.originalEvent;

		this.isImmediatePropagationStopped = returnTrue;

		if ( e && !this.isSimulated ) {
			e.stopImmediatePropagation();
		}

		this.stopPropagation();
	}
};

// Includes all common event props including KeyEvent and MouseEvent specific props
jQuery.each( {
	altKey: true,
	bubbles: true,
	cancelable: true,
	changedTouches: true,
	ctrlKey: true,
	detail: true,
	eventPhase: true,
	metaKey: true,
	pageX: true,
	pageY: true,
	shiftKey: true,
	view: true,
	"char": true,
	code: true,
	charCode: true,
	key: true,
	keyCode: true,
	button: true,
	buttons: true,
	clientX: true,
	clientY: true,
	offsetX: true,
	offsetY: true,
	pointerId: true,
	pointerType: true,
	screenX: true,
	screenY: true,
	targetTouches: true,
	toElement: true,
	touches: true,
	which: true
}, jQuery.event.addProp );

jQuery.each( { focus: "focusin", blur: "focusout" }, function( type, delegateType ) {

	function focusMappedHandler( nativeEvent ) {
		if ( document.documentMode ) {

			// Support: IE 11+
			// Attach a single focusin/focusout handler on the document while someone wants
			// focus/blur. This is because the former are synchronous in IE while the latter
			// are async. In other browsers, all those handlers are invoked synchronously.

			// `handle` from private data would already wrap the event, but we need
			// to change the `type` here.
			var handle = dataPriv.get( this, "handle" ),
				event = jQuery.event.fix( nativeEvent );
			event.type = nativeEvent.type === "focusin" ? "focus" : "blur";
			event.isSimulated = true;

			// First, handle focusin/focusout
			handle( nativeEvent );

			// ...then, handle focus/blur
			//
			// focus/blur don't bubble while focusin/focusout do; simulate the former by only
			// invoking the handler at the lower level.
			if ( event.target === event.currentTarget ) {

				// The setup part calls `leverageNative`, which, in turn, calls
				// `jQuery.event.add`, so event handle will already have been set
				// by this point.
				handle( event );
			}
		} else {

			// For non-IE browsers, attach a single capturing handler on the document
			// while someone wants focusin/focusout.
			jQuery.event.simulate( delegateType, nativeEvent.target,
				jQuery.event.fix( nativeEvent ) );
		}
	}

	jQuery.event.special[ type ] = {

		// Utilize native event if possible so blur/focus sequence is correct
		setup: function() {

			var attaches;

			// Claim the first handler
			// dataPriv.set( this, "focus", ... )
			// dataPriv.set( this, "blur", ... )
			leverageNative( this, type, true );

			if ( document.documentMode ) {

				// Support: IE 9 - 11+
				// We use the same native handler for focusin & focus (and focusout & blur)
				// so we need to coordinate setup & teardown parts between those events.
				// Use `delegateType` as the key as `type` is already used by `leverageNative`.
				attaches = dataPriv.get( this, delegateType );
				if ( !attaches ) {
					this.addEventListener( delegateType, focusMappedHandler );
				}
				dataPriv.set( this, delegateType, ( attaches || 0 ) + 1 );
			} else {

				// Return false to allow normal processing in the caller
				return false;
			}
		},
		trigger: function() {

			// Force setup before trigger
			leverageNative( this, type );

			// Return non-false to allow normal event-path propagation
			return true;
		},

		teardown: function() {
			var attaches;

			if ( document.documentMode ) {
				attaches = dataPriv.get( this, delegateType ) - 1;
				if ( !attaches ) {
					this.removeEventListener( delegateType, focusMappedHandler );
					dataPriv.remove( this, delegateType );
				} else {
					dataPriv.set( this, delegateType, attaches );
				}
			} else {

				// Return false to indicate standard teardown should be applied
				return false;
			}
		},

		// Suppress native focus or blur if we're currently inside
		// a leveraged native-event stack
		_default: function( event ) {
			return dataPriv.get( event.target, type );
		},

		delegateType: delegateType
	};

	// Support: Firefox <=44
	// Firefox doesn't have focus(in | out) events
	// Related ticket - https://bugzilla.mozilla.org/show_bug.cgi?id=687787
	//
	// Support: Chrome <=48 - 49, Safari <=9.0 - 9.1
	// focus(in | out) events fire after focus & blur events,
	// which is spec violation - http://www.w3.org/TR/DOM-Level-3-Events/#events-focusevent-event-order
	// Related ticket - https://bugs.chromium.org/p/chromium/issues/detail?id=449857
	//
	// Support: IE 9 - 11+
	// To preserve relative focusin/focus & focusout/blur event order guaranteed on the 3.x branch,
	// attach a single handler for both events in IE.
	jQuery.event.special[ delegateType ] = {
		setup: function() {

			// Handle: regular nodes (via `this.ownerDocument`), window
			// (via `this.document`) & document (via `this`).
			var doc = this.ownerDocument || this.document || this,
				dataHolder = document.documentMode ? this : doc,
				attaches = dataPriv.get( dataHolder, delegateType );

			// Support: IE 9 - 11+
			// We use the same native handler for focusin & focus (and focusout & blur)
			// so we need to coordinate setup & teardown parts between those events.
			// Use `delegateType` as the key as `type` is already used by `leverageNative`.
			if ( !attaches ) {
				if ( document.documentMode ) {
					this.addEventListener( delegateType, focusMappedHandler );
				} else {
					doc.addEventListener( type, focusMappedHandler, true );
				}
			}
			dataPriv.set( dataHolder, delegateType, ( attaches || 0 ) + 1 );
		},
		teardown: function() {
			var doc = this.ownerDocument || this.document || this,
				dataHolder = document.documentMode ? this : doc,
				attaches = dataPriv.get( dataHolder, delegateType ) - 1;

			if ( !attaches ) {
				if ( document.documentMode ) {
					this.removeEventListener( delegateType, focusMappedHandler );
				} else {
					doc.removeEventListener( type, focusMappedHandler, true );
				}
				dataPriv.remove( dataHolder, delegateType );
			} else {
				dataPriv.set( dataHolder, delegateType, attaches );
			}
		}
	};
} );

// Create mouseenter/leave events using mouseover/out and event-time checks
// so that event delegation works in jQuery.
// Do the same for pointerenter/pointerleave and pointerover/pointerout
//
// Support: Safari 7 only
// Safari sends mouseenter too often; see:
// https://bugs.chromium.org/p/chromium/issues/detail?id=470258
// for the description of the bug (it existed in older Chrome versions as well).
jQuery.each( {
	mouseenter: "mouseover",
	mouseleave: "mouseout",
	pointerenter: "pointerover",
	pointerleave: "pointerout"
}, function( orig, fix ) {
	jQuery.event.special[ orig ] = {
		delegateType: fix,
		bindType: fix,

		handle: function( event ) {
			var ret,
				target = this,
				related = event.relatedTarget,
				handleObj = event.handleObj;

			// For mouseenter/leave call the handler if related is outside the target.
			// NB: No relatedTarget if the mouse left/entered the browser window
			if ( !related || ( related !== target && !jQuery.contains( target, related ) ) ) {
				event.type = handleObj.origType;
				ret = handleObj.handler.apply( this, arguments );
				event.type = fix;
			}
			return ret;
		}
	};
} );

jQuery.fn.extend( {

	on: function( types, selector, data, fn ) {
		return on( this, types, selector, data, fn );
	},
	one: function( types, selector, data, fn ) {
		return on( this, types, selector, data, fn, 1 );
	},
	off: function( types, selector, fn ) {
		var handleObj, type;
		if ( types && types.preventDefault && types.handleObj ) {

			// ( event )  dispatched jQuery.Event
			handleObj = types.handleObj;
			jQuery( types.delegateTarget ).off(
				handleObj.namespace ?
					handleObj.origType + "." + handleObj.namespace :
					handleObj.origType,
				handleObj.selector,
				handleObj.handler
			);
			return this;
		}
		if ( typeof types === "object" ) {

			// ( types-object [, selector] )
			for ( type in types ) {
				this.off( type, selector, types[ type ] );
			}
			return this;
		}
		if ( selector === false || typeof selector === "function" ) {

			// ( types [, fn] )
			fn = selector;
			selector = undefined;
		}
		if ( fn === false ) {
			fn = returnFalse;
		}
		return this.each( function() {
			jQuery.event.remove( this, types, fn, selector );
		} );
	}
} );


var

	// Support: IE <=10 - 11, Edge 12 - 13 only
	// In IE/Edge using regex groups here causes severe slowdowns.
	// See https://connect.microsoft.com/IE/feedback/details/1736512/
	rnoInnerhtml = /<script|<style|<link/i,

	// checked="checked" or checked
	rchecked = /checked\s*(?:[^=]|=\s*.checked.)/i,

	rcleanScript = /^\s*<!\[CDATA\[|\]\]>\s*$/g;

// Prefer a tbody over its parent table for containing new rows
function manipulationTarget( elem, content ) {
	if ( nodeName( elem, "table" ) &&
		nodeName( content.nodeType !== 11 ? content : content.firstChild, "tr" ) ) {

		return jQuery( elem ).children( "tbody" )[ 0 ] || elem;
	}

	return elem;
}

// Replace/restore the type attribute of script elements for safe DOM manipulation
function disableScript( elem ) {
	elem.type = ( elem.getAttribute( "type" ) !== null ) + "/" + elem.type;
	return elem;
}
function restoreScript( elem ) {
	if ( ( elem.type || "" ).slice( 0, 5 ) === "true/" ) {
		elem.type = elem.type.slice( 5 );
	} else {
		elem.removeAttribute( "type" );
	}

	return elem;
}

function cloneCopyEvent( src, dest ) {
	var i, l, type, pdataOld, udataOld, udataCur, events;

	if ( dest.nodeType !== 1 ) {
		return;
	}

	// 1. Copy private data: events, handlers, etc.
	if ( dataPriv.hasData( src ) ) {
		pdataOld = dataPriv.get( src );
		events = pdataOld.events;

		if ( events ) {
			dataPriv.remove( dest, "handle events" );

			for ( type in events ) {
				for ( i = 0, l = events[ type ].length; i < l; i++ ) {
					jQuery.event.add( dest, type, events[ type ][ i ] );
				}
			}
		}
	}

	// 2. Copy user data
	if ( dataUser.hasData( src ) ) {
		udataOld = dataUser.access( src );
		udataCur = jQuery.extend( {}, udataOld );

		dataUser.set( dest, udataCur );
	}
}

// Fix IE bugs, see support tests
function fixInput( src, dest ) {
	var nodeName = dest.nodeName.toLowerCase();

	// Fails to persist the checked state of a cloned checkbox or radio button.
	if ( nodeName === "input" && rcheckableType.test( src.type ) ) {
		dest.checked = src.checked;

	// Fails to return the selected option to the default selected state when cloning options
	} else if ( nodeName === "input" || nodeName === "textarea" ) {
		dest.defaultValue = src.defaultValue;
	}
}

function domManip( collection, args, callback, ignored ) {

	// Flatten any nested arrays
	args = flat( args );

	var fragment, first, scripts, hasScripts, node, doc,
		i = 0,
		l = collection.length,
		iNoClone = l - 1,
		value = args[ 0 ],
		valueIsFunction = isFunction( value );

	// We can't cloneNode fragments that contain checked, in WebKit
	if ( valueIsFunction ||
			( l > 1 && typeof value === "string" &&
				!support.checkClone && rchecked.test( value ) ) ) {
		return collection.each( function( index ) {
			var self = collection.eq( index );
			if ( valueIsFunction ) {
				args[ 0 ] = value.call( this, index, self.html() );
			}
			domManip( self, args, callback, ignored );
		} );
	}

	if ( l ) {
		fragment = buildFragment( args, collection[ 0 ].ownerDocument, false, collection, ignored );
		first = fragment.firstChild;

		if ( fragment.childNodes.length === 1 ) {
			fragment = first;
		}

		// Require either new content or an interest in ignored elements to invoke the callback
		if ( first || ignored ) {
			scripts = jQuery.map( getAll( fragment, "script" ), disableScript );
			hasScripts = scripts.length;

			// Use the original fragment for the last item
			// instead of the first because it can end up
			// being emptied incorrectly in certain situations (trac-8070).
			for ( ; i < l; i++ ) {
				node = fragment;

				if ( i !== iNoClone ) {
					node = jQuery.clone( node, true, true );

					// Keep references to cloned scripts for later restoration
					if ( hasScripts ) {

						// Support: Android <=4.0 only, PhantomJS 1 only
						// push.apply(_, arraylike) throws on ancient WebKit
						jQuery.merge( scripts, getAll( node, "script" ) );
					}
				}

				callback.call( collection[ i ], node, i );
			}

			if ( hasScripts ) {
				doc = scripts[ scripts.length - 1 ].ownerDocument;

				// Re-enable scripts
				jQuery.map( scripts, restoreScript );

				// Evaluate executable scripts on first document insertion
				for ( i = 0; i < hasScripts; i++ ) {
					node = scripts[ i ];
					if ( rscriptType.test( node.type || "" ) &&
						!dataPriv.access( node, "globalEval" ) &&
						jQuery.contains( doc, node ) ) {

						if ( node.src && ( node.type || "" ).toLowerCase()  !== "module" ) {

							// Optional AJAX dependency, but won't run scripts if not present
							if ( jQuery._evalUrl && !node.noModule ) {
								jQuery._evalUrl( node.src, {
									nonce: node.nonce || node.getAttribute( "nonce" )
								}, doc );
							}
						} else {

							// Unwrap a CDATA section containing script contents. This shouldn't be
							// needed as in XML documents they're already not visible when
							// inspecting element contents and in HTML documents they have no
							// meaning but we're preserving that logic for backwards compatibility.
							// This will be removed completely in 4.0. See gh-4904.
							DOMEval( node.textContent.replace( rcleanScript, "" ), node, doc );
						}
					}
				}
			}
		}
	}

	return collection;
}

function remove( elem, selector, keepData ) {
	var node,
		nodes = selector ? jQuery.filter( selector, elem ) : elem,
		i = 0;

	for ( ; ( node = nodes[ i ] ) != null; i++ ) {
		if ( !keepData && node.nodeType === 1 ) {
			jQuery.cleanData( getAll( node ) );
		}

		if ( node.parentNode ) {
			if ( keepData && isAttached( node ) ) {
				setGlobalEval( getAll( node, "script" ) );
			}
			node.parentNode.removeChild( node );
		}
	}

	return elem;
}

jQuery.extend( {
	htmlPrefilter: function( html ) {
		return html;
	},

	clone: function( elem, dataAndEvents, deepDataAndEvents ) {
		var i, l, srcElements, destElements,
			clone = elem.cloneNode( true ),
			inPage = isAttached( elem );

		// Fix IE cloning issues
		if ( !support.noCloneChecked && ( elem.nodeType === 1 || elem.nodeType === 11 ) &&
				!jQuery.isXMLDoc( elem ) ) {

			// We eschew jQuery#find here for performance reasons:
			// https://jsperf.com/getall-vs-sizzle/2
			destElements = getAll( clone );
			srcElements = getAll( elem );

			for ( i = 0, l = srcElements.length; i < l; i++ ) {
				fixInput( srcElements[ i ], destElements[ i ] );
			}
		}

		// Copy the events from the original to the clone
		if ( dataAndEvents ) {
			if ( deepDataAndEvents ) {
				srcElements = srcElements || getAll( elem );
				destElements = destElements || getAll( clone );

				for ( i = 0, l = srcElements.length; i < l; i++ ) {
					cloneCopyEvent( srcElements[ i ], destElements[ i ] );
				}
			} else {
				cloneCopyEvent( elem, clone );
			}
		}

		// Preserve script evaluation history
		destElements = getAll( clone, "script" );
		if ( destElements.length > 0 ) {
			setGlobalEval( destElements, !inPage && getAll( elem, "script" ) );
		}

		// Return the cloned set
		return clone;
	},

	cleanData: function( elems ) {
		var data, elem, type,
			special = jQuery.event.special,
			i = 0;

		for ( ; ( elem = elems[ i ] ) !== undefined; i++ ) {
			if ( acceptData( elem ) ) {
				if ( ( data = elem[ dataPriv.expando ] ) ) {
					if ( data.events ) {
						for ( type in data.events ) {
							if ( special[ type ] ) {
								jQuery.event.remove( elem, type );

							// This is a shortcut to avoid jQuery.event.remove's overhead
							} else {
								jQuery.removeEvent( elem, type, data.handle );
							}
						}
					}

					// Support: Chrome <=35 - 45+
					// Assign undefined instead of using delete, see Data#remove
					elem[ dataPriv.expando ] = undefined;
				}
				if ( elem[ dataUser.expando ] ) {

					// Support: Chrome <=35 - 45+
					// Assign undefined instead of using delete, see Data#remove
					elem[ dataUser.expando ] = undefined;
				}
			}
		}
	}
} );

jQuery.fn.extend( {
	detach: function( selector ) {
		return remove( this, selector, true );
	},

	remove: function( selector ) {
		return remove( this, selector );
	},

	text: function( value ) {
		return access( this, function( value ) {
			return value === undefined ?
				jQuery.text( this ) :
				this.empty().each( function() {
					if ( this.nodeType === 1 || this.nodeType === 11 || this.nodeType === 9 ) {
						this.textContent = value;
					}
				} );
		}, null, value, arguments.length );
	},

	append: function() {
		return domManip( this, arguments, function( elem ) {
			if ( this.nodeType === 1 || this.nodeType === 11 || this.nodeType === 9 ) {
				var target = manipulationTarget( this, elem );
				target.appendChild( elem );
			}
		} );
	},

	prepend: function() {
		return domManip( this, arguments, function( elem ) {
			if ( this.nodeType === 1 || this.nodeType === 11 || this.nodeType === 9 ) {
				var target = manipulationTarget( this, elem );
				target.insertBefore( elem, target.firstChild );
			}
		} );
	},

	before: function() {
		return domManip( this, arguments, function( elem ) {
			if ( this.parentNode ) {
				this.parentNode.insertBefore( elem, this );
			}
		} );
	},

	after: function() {
		return domManip( this, arguments, function( elem ) {
			if ( this.parentNode ) {
				this.parentNode.insertBefore( elem, this.nextSibling );
			}
		} );
	},

	empty: function() {
		var elem,
			i = 0;

		for ( ; ( elem = this[ i ] ) != null; i++ ) {
			if ( elem.nodeType === 1 ) {

				// Prevent memory leaks
				jQuery.cleanData( getAll( elem, false ) );

				// Remove any remaining nodes
				elem.textContent = "";
			}
		}

		return this;
	},

	clone: function( dataAndEvents, deepDataAndEvents ) {
		dataAndEvents = dataAndEvents == null ? false : dataAndEvents;
		deepDataAndEvents = deepDataAndEvents == null ? dataAndEvents : deepDataAndEvents;

		return this.map( function() {
			return jQuery.clone( this, dataAndEvents, deepDataAndEvents );
		} );
	},

	html: function( value ) {
		return access( this, function( value ) {
			var elem = this[ 0 ] || {},
				i = 0,
				l = this.length;

			if ( value === undefined && elem.nodeType === 1 ) {
				return elem.innerHTML;
			}

			// See if we can take a shortcut and just use innerHTML
			if ( typeof value === "string" && !rnoInnerhtml.test( value ) &&
				!wrapMap[ ( rtagName.exec( value ) || [ "", "" ] )[ 1 ].toLowerCase() ] ) {

				value = jQuery.htmlPrefilter( value );

				try {
					for ( ; i < l; i++ ) {
						elem = this[ i ] || {};

						// Remove element nodes and prevent memory leaks
						if ( elem.nodeType === 1 ) {
							jQuery.cleanData( getAll( elem, false ) );
							elem.innerHTML = value;
						}
					}

					elem = 0;

				// If using innerHTML throws an exception, use the fallback method
				} catch ( e ) {}
			}

			if ( elem ) {
				this.empty().append( value );
			}
		}, null, value, arguments.length );
	},

	replaceWith: function() {
		var ignored = [];

		// Make the changes, replacing each non-ignored context element with the new content
		return domManip( this, arguments, function( elem ) {
			var parent = this.parentNode;

			if ( jQuery.inArray( this, ignored ) < 0 ) {
				jQuery.cleanData( getAll( this ) );
				if ( parent ) {
					parent.replaceChild( elem, this );
				}
			}

		// Force callback invocation
		}, ignored );
	}
} );

jQuery.each( {
	appendTo: "append",
	prependTo: "prepend",
	insertBefore: "before",
	insertAfter: "after",
	replaceAll: "replaceWith"
}, function( name, original ) {
	jQuery.fn[ name ] = function( selector ) {
		var elems,
			ret = [],
			insert = jQuery( selector ),
			last = insert.length - 1,
			i = 0;

		for ( ; i <= last; i++ ) {
			elems = i === last ? this : this.clone( true );
			jQuery( insert[ i ] )[ original ]( elems );

			// Support: Android <=4.0 only, PhantomJS 1 only
			// .get() because push.apply(_, arraylike) throws on ancient WebKit
			push.apply( ret, elems.get() );
		}

		return this.pushStack( ret );
	};
} );
var rnumnonpx = new RegExp( "^(" + pnum + ")(?!px)[a-z%]+$", "i" );

var rcustomProp = /^--/;


var getStyles = function( elem ) {

		// Support: IE <=11 only, Firefox <=30 (trac-15098, trac-14150)
		// IE throws on elements created in popups
		// FF meanwhile throws on frame elements through "defaultView.getComputedStyle"
		var view = elem.ownerDocument.defaultView;

		if ( !view || !view.opener ) {
			view = window;
		}

		return view.getComputedStyle( elem );
	};

var swap = function( elem, options, callback ) {
	var ret, name,
		old = {};

	// Remember the old values, and insert the new ones
	for ( name in options ) {
		old[ name ] = elem.style[ name ];
		elem.style[ name ] = options[ name ];
	}

	ret = callback.call( elem );

	// Revert the old values
	for ( name in options ) {
		elem.style[ name ] = old[ name ];
	}

	return ret;
};


var rboxStyle = new RegExp( cssExpand.join( "|" ), "i" );



( function() {

	// Executing both pixelPosition & boxSizingReliable tests require only one layout
	// so they're executed at the same time to save the second computation.
	function computeStyleTests() {

		// This is a singleton, we need to execute it only once
		if ( !div ) {
			return;
		}

		container.style.cssText = "position:absolute;left:-11111px;width:60px;" +
			"margin-top:1px;padding:0;border:0";
		div.style.cssText =
			"position:relative;display:block;box-sizing:border-box;overflow:scroll;" +
			"margin:auto;border:1px;padding:1px;" +
			"width:60%;top:1%";
		documentElement.appendChild( container ).appendChild( div );

		var divStyle = window.getComputedStyle( div );
		pixelPositionVal = divStyle.top !== "1%";

		// Support: Android 4.0 - 4.3 only, Firefox <=3 - 44
		reliableMarginLeftVal = roundPixelMeasures( divStyle.marginLeft ) === 12;

		// Support: Android 4.0 - 4.3 only, Safari <=9.1 - 10.1, iOS <=7.0 - 9.3
		// Some styles come back with percentage values, even though they shouldn't
		div.style.right = "60%";
		pixelBoxStylesVal = roundPixelMeasures( divStyle.right ) === 36;

		// Support: IE 9 - 11 only
		// Detect misreporting of content dimensions for box-sizing:border-box elements
		boxSizingReliableVal = roundPixelMeasures( divStyle.width ) === 36;

		// Support: IE 9 only
		// Detect overflow:scroll screwiness (gh-3699)
		// Support: Chrome <=64
		// Don't get tricked when zoom affects offsetWidth (gh-4029)
		div.style.position = "absolute";
		scrollboxSizeVal = roundPixelMeasures( div.offsetWidth / 3 ) === 12;

		documentElement.removeChild( container );

		// Nullify the div so it wouldn't be stored in the memory and
		// it will also be a sign that checks already performed
		div = null;
	}

	function roundPixelMeasures( measure ) {
		return Math.round( parseFloat( measure ) );
	}

	var pixelPositionVal, boxSizingReliableVal, scrollboxSizeVal, pixelBoxStylesVal,
		reliableTrDimensionsVal, reliableMarginLeftVal,
		container = document.createElement( "div" ),
		div = document.createElement( "div" );

	// Finish early in limited (non-browser) environments
	if ( !div.style ) {
		return;
	}

	// Support: IE <=9 - 11 only
	// Style of cloned element affects source element cloned (trac-8908)
	div.style.backgroundClip = "content-box";
	div.cloneNode( true ).style.backgroundClip = "";
	support.clearCloneStyle = div.style.backgroundClip === "content-box";

	jQuery.extend( support, {
		boxSizingReliable: function() {
			computeStyleTests();
			return boxSizingReliableVal;
		},
		pixelBoxStyles: function() {
			computeStyleTests();
			return pixelBoxStylesVal;
		},
		pixelPosition: function() {
			computeStyleTests();
			return pixelPositionVal;
		},
		reliableMarginLeft: function() {
			computeStyleTests();
			return reliableMarginLeftVal;
		},
		scrollboxSize: function() {
			computeStyleTests();
			return scrollboxSizeVal;
		},

		// Support: IE 9 - 11+, Edge 15 - 18+
		// IE/Edge misreport `getComputedStyle` of table rows with width/height
		// set in CSS while `offset*` properties report correct values.
		// Behavior in IE 9 is more subtle than in newer versions & it passes
		// some versions of this test; make sure not to make it pass there!
		//
		// Support: Firefox 70+
		// Only Firefox includes border widths
		// in computed dimensions. (gh-4529)
		reliableTrDimensions: function() {
			var table, tr, trChild, trStyle;
			if ( reliableTrDimensionsVal == null ) {
				table = document.createElement( "table" );
				tr = document.createElement( "tr" );
				trChild = document.createElement( "div" );

				table.style.cssText = "position:absolute;left:-11111px;border-collapse:separate";
				tr.style.cssText = "box-sizing:content-box;border:1px solid";

				// Support: Chrome 86+
				// Height set through cssText does not get applied.
				// Computed height then comes back as 0.
				tr.style.height = "1px";
				trChild.style.height = "9px";

				// Support: Android 8 Chrome 86+
				// In our bodyBackground.html iframe,
				// display for all div elements is set to "inline",
				// which causes a problem only in Android 8 Chrome 86.
				// Ensuring the div is `display: block`
				// gets around this issue.
				trChild.style.display = "block";

				documentElement
					.appendChild( table )
					.appendChild( tr )
					.appendChild( trChild );

				trStyle = window.getComputedStyle( tr );
				reliableTrDimensionsVal = ( parseInt( trStyle.height, 10 ) +
					parseInt( trStyle.borderTopWidth, 10 ) +
					parseInt( trStyle.borderBottomWidth, 10 ) ) === tr.offsetHeight;

				documentElement.removeChild( table );
			}
			return reliableTrDimensionsVal;
		}
	} );
} )();


function curCSS( elem, name, computed ) {
	var width, minWidth, maxWidth, ret,
		isCustomProp = rcustomProp.test( name ),

		// Support: Firefox 51+
		// Retrieving style before computed somehow
		// fixes an issue with getting wrong values
		// on detached elements
		style = elem.style;

	computed = computed || getStyles( elem );

	// getPropertyValue is needed for:
	//   .css('filter') (IE 9 only, trac-12537)
	//   .css('--customProperty) (gh-3144)
	if ( computed ) {

		// Support: IE <=9 - 11+
		// IE only supports `"float"` in `getPropertyValue`; in computed styles
		// it's only available as `"cssFloat"`. We no longer modify properties
		// sent to `.css()` apart from camelCasing, so we need to check both.
		// Normally, this would create difference in behavior: if
		// `getPropertyValue` returns an empty string, the value returned
		// by `.css()` would be `undefined`. This is usually the case for
		// disconnected elements. However, in IE even disconnected elements
		// with no styles return `"none"` for `getPropertyValue( "float" )`
		ret = computed.getPropertyValue( name ) || computed[ name ];

		if ( isCustomProp && ret ) {

			// Support: Firefox 105+, Chrome <=105+
			// Spec requires trimming whitespace for custom properties (gh-4926).
			// Firefox only trims leading whitespace. Chrome just collapses
			// both leading & trailing whitespace to a single space.
			//
			// Fall back to `undefined` if empty string returned.
			// This collapses a missing definition with property defined
			// and set to an empty string but there's no standard API
			// allowing us to differentiate them without a performance penalty
			// and returning `undefined` aligns with older jQuery.
			//
			// rtrimCSS treats U+000D CARRIAGE RETURN and U+000C FORM FEED
			// as whitespace while CSS does not, but this is not a problem
			// because CSS preprocessing replaces them with U+000A LINE FEED
			// (which *is* CSS whitespace)
			// https://www.w3.org/TR/css-syntax-3/#input-preprocessing
			ret = ret.replace( rtrimCSS, "$1" ) || undefined;
		}

		if ( ret === "" && !isAttached( elem ) ) {
			ret = jQuery.style( elem, name );
		}

		// A tribute to the "awesome hack by Dean Edwards"
		// Android Browser returns percentage for some values,
		// but width seems to be reliably pixels.
		// This is against the CSSOM draft spec:
		// https://drafts.csswg.org/cssom/#resolved-values
		if ( !support.pixelBoxStyles() && rnumnonpx.test( ret ) && rboxStyle.test( name ) ) {

			// Remember the original values
			width = style.width;
			minWidth = style.minWidth;
			maxWidth = style.maxWidth;

			// Put in the new values to get a computed value out
			style.minWidth = style.maxWidth = style.width = ret;
			ret = computed.width;

			// Revert the changed values
			style.width = width;
			style.minWidth = minWidth;
			style.maxWidth = maxWidth;
		}
	}

	return ret !== undefined ?

		// Support: IE <=9 - 11 only
		// IE returns zIndex value as an integer.
		ret + "" :
		ret;
}


function addGetHookIf( conditionFn, hookFn ) {

	// Define the hook, we'll check on the first run if it's really needed.
	return {
		get: function() {
			if ( conditionFn() ) {

				// Hook not needed (or it's not possible to use it due
				// to missing dependency), remove it.
				delete this.get;
				return;
			}

			// Hook needed; redefine it so that the support test is not executed again.
			return ( this.get = hookFn ).apply( this, arguments );
		}
	};
}


var cssPrefixes = [ "Webkit", "Moz", "ms" ],
	emptyStyle = document.createElement( "div" ).style,
	vendorProps = {};

// Return a vendor-prefixed property or undefined
function vendorPropName( name ) {

	// Check for vendor prefixed names
	var capName = name[ 0 ].toUpperCase() + name.slice( 1 ),
		i = cssPrefixes.length;

	while ( i-- ) {
		name = cssPrefixes[ i ] + capName;
		if ( name in emptyStyle ) {
			return name;
		}
	}
}

// Return a potentially-mapped jQuery.cssProps or vendor prefixed property
function finalPropName( name ) {
	var final = jQuery.cssProps[ name ] || vendorProps[ name ];

	if ( final ) {
		return final;
	}
	if ( name in emptyStyle ) {
		return name;
	}
	return vendorProps[ name ] = vendorPropName( name ) || name;
}


var

	// Swappable if display is none or starts with table
	// except "table", "table-cell", or "table-caption"
	// See here for display values: https://developer.mozilla.org/en-US/docs/CSS/display
	rdisplayswap = /^(none|table(?!-c[ea]).+)/,
	cssShow = { position: "absolute", visibility: "hidden", display: "block" },
	cssNormalTransform = {
		letterSpacing: "0",
		fontWeight: "400"
	};

function setPositiveNumber( _elem, value, subtract ) {

	// Any relative (+/-) values have already been
	// normalized at this point
	var matches = rcssNum.exec( value );
	return matches ?

		// Guard against undefined "subtract", e.g., when used as in cssHooks
		Math.max( 0, matches[ 2 ] - ( subtract || 0 ) ) + ( matches[ 3 ] || "px" ) :
		value;
}

function boxModelAdjustment( elem, dimension, box, isBorderBox, styles, computedVal ) {
	var i = dimension === "width" ? 1 : 0,
		extra = 0,
		delta = 0,
		marginDelta = 0;

	// Adjustment may not be necessary
	if ( box === ( isBorderBox ? "border" : "content" ) ) {
		return 0;
	}

	for ( ; i < 4; i += 2 ) {

		// Both box models exclude margin
		// Count margin delta separately to only add it after scroll gutter adjustment.
		// This is needed to make negative margins work with `outerHeight( true )` (gh-3982).
		if ( box === "margin" ) {
			marginDelta += jQuery.css( elem, box + cssExpand[ i ], true, styles );
		}

		// If we get here with a content-box, we're seeking "padding" or "border" or "margin"
		if ( !isBorderBox ) {

			// Add padding
			delta += jQuery.css( elem, "padding" + cssExpand[ i ], true, styles );

			// For "border" or "margin", add border
			if ( box !== "padding" ) {
				delta += jQuery.css( elem, "border" + cssExpand[ i ] + "Width", true, styles );

			// But still keep track of it otherwise
			} else {
				extra += jQuery.css( elem, "border" + cssExpand[ i ] + "Width", true, styles );
			}

		// If we get here with a border-box (content + padding + border), we're seeking "content" or
		// "padding" or "margin"
		} else {

			// For "content", subtract padding
			if ( box === "content" ) {
				delta -= jQuery.css( elem, "padding" + cssExpand[ i ], true, styles );
			}

			// For "content" or "padding", subtract border
			if ( box !== "margin" ) {
				delta -= jQuery.css( elem, "border" + cssExpand[ i ] + "Width", true, styles );
			}
		}
	}

	// Account for positive content-box scroll gutter when requested by providing computedVal
	if ( !isBorderBox && computedVal >= 0 ) {

		// offsetWidth/offsetHeight is a rounded sum of content, padding, scroll gutter, and border
		// Assuming integer scroll gutter, subtract the rest and round down
		delta += Math.max( 0, Math.ceil(
			elem[ "offset" + dimension[ 0 ].toUpperCase() + dimension.slice( 1 ) ] -
			computedVal -
			delta -
			extra -
			0.5

		// If offsetWidth/offsetHeight is unknown, then we can't determine content-box scroll gutter
		// Use an explicit zero to avoid NaN (gh-3964)
		) ) || 0;
	}

	return delta + marginDelta;
}

function getWidthOrHeight( elem, dimension, extra ) {

	// Start with computed style
	var styles = getStyles( elem ),

		// To avoid forcing a reflow, only fetch boxSizing if we need it (gh-4322).
		// Fake content-box until we know it's needed to know the true value.
		boxSizingNeeded = !support.boxSizingReliable() || extra,
		isBorderBox = boxSizingNeeded &&
			jQuery.css( elem, "boxSizing", false, styles ) === "border-box",
		valueIsBorderBox = isBorderBox,

		val = curCSS( elem, dimension, styles ),
		offsetProp = "offset" + dimension[ 0 ].toUpperCase() + dimension.slice( 1 );

	// Support: Firefox <=54
	// Return a confounding non-pixel value or feign ignorance, as appropriate.
	if ( rnumnonpx.test( val ) ) {
		if ( !extra ) {
			return val;
		}
		val = "auto";
	}


	// Support: IE 9 - 11 only
	// Use offsetWidth/offsetHeight for when box sizing is unreliable.
	// In those cases, the computed value can be trusted to be border-box.
	if ( ( !support.boxSizingReliable() && isBorderBox ||

		// Support: IE 10 - 11+, Edge 15 - 18+
		// IE/Edge misreport `getComputedStyle` of table rows with width/height
		// set in CSS while `offset*` properties report correct values.
		// Interestingly, in some cases IE 9 doesn't suffer from this issue.
		!support.reliableTrDimensions() && nodeName( elem, "tr" ) ||

		// Fall back to offsetWidth/offsetHeight when value is "auto"
		// This happens for inline elements with no explicit setting (gh-3571)
		val === "auto" ||

		// Support: Android <=4.1 - 4.3 only
		// Also use offsetWidth/offsetHeight for misreported inline dimensions (gh-3602)
		!parseFloat( val ) && jQuery.css( elem, "display", false, styles ) === "inline" ) &&

		// Make sure the element is visible & connected
		elem.getClientRects().length ) {

		isBorderBox = jQuery.css( elem, "boxSizing", false, styles ) === "border-box";

		// Where available, offsetWidth/offsetHeight approximate border box dimensions.
		// Where not available (e.g., SVG), assume unreliable box-sizing and interpret the
		// retrieved value as a content box dimension.
		valueIsBorderBox = offsetProp in elem;
		if ( valueIsBorderBox ) {
			val = elem[ offsetProp ];
		}
	}

	// Normalize "" and auto
	val = parseFloat( val ) || 0;

	// Adjust for the element's box model
	return ( val +
		boxModelAdjustment(
			elem,
			dimension,
			extra || ( isBorderBox ? "border" : "content" ),
			valueIsBorderBox,
			styles,

			// Provide the current computed size to request scroll gutter calculation (gh-3589)
			val
		)
	) + "px";
}

jQuery.extend( {

	// Add in style property hooks for overriding the default
	// behavior of getting and setting a style property
	cssHooks: {
		opacity: {
			get: function( elem, computed ) {
				if ( computed ) {

					// We should always get a number back from opacity
					var ret = curCSS( elem, "opacity" );
					return ret === "" ? "1" : ret;
				}
			}
		}
	},

	// Don't automatically add "px" to these possibly-unitless properties
	cssNumber: {
		animationIterationCount: true,
		aspectRatio: true,
		borderImageSlice: true,
		columnCount: true,
		flexGrow: true,
		flexShrink: true,
		fontWeight: true,
		gridArea: true,
		gridColumn: true,
		gridColumnEnd: true,
		gridColumnStart: true,
		gridRow: true,
		gridRowEnd: true,
		gridRowStart: true,
		lineHeight: true,
		opacity: true,
		order: true,
		orphans: true,
		scale: true,
		widows: true,
		zIndex: true,
		zoom: true,

		// SVG-related
		fillOpacity: true,
		floodOpacity: true,
		stopOpacity: true,
		strokeMiterlimit: true,
		strokeOpacity: true
	},

	// Add in properties whose names you wish to fix before
	// setting or getting the value
	cssProps: {},

	// Get and set the style property on a DOM Node
	style: function( elem, name, value, extra ) {

		// Don't set styles on text and comment nodes
		if ( !elem || elem.nodeType === 3 || elem.nodeType === 8 || !elem.style ) {
			return;
		}

		// Make sure that we're working with the right name
		var ret, type, hooks,
			origName = camelCase( name ),
			isCustomProp = rcustomProp.test( name ),
			style = elem.style;

		// Make sure that we're working with the right name. We don't
		// want to query the value if it is a CSS custom property
		// since they are user-defined.
		if ( !isCustomProp ) {
			name = finalPropName( origName );
		}

		// Gets hook for the prefixed version, then unprefixed version
		hooks = jQuery.cssHooks[ name ] || jQuery.cssHooks[ origName ];

		// Check if we're setting a value
		if ( value !== undefined ) {
			type = typeof value;

			// Convert "+=" or "-=" to relative numbers (trac-7345)
			if ( type === "string" && ( ret = rcssNum.exec( value ) ) && ret[ 1 ] ) {
				value = adjustCSS( elem, name, ret );

				// Fixes bug trac-9237
				type = "number";
			}

			// Make sure that null and NaN values aren't set (trac-7116)
			if ( value == null || value !== value ) {
				return;
			}

			// If a number was passed in, add the unit (except for certain CSS properties)
			// The isCustomProp check can be removed in jQuery 4.0 when we only auto-append
			// "px" to a few hardcoded values.
			if ( type === "number" && !isCustomProp ) {
				value += ret && ret[ 3 ] || ( jQuery.cssNumber[ origName ] ? "" : "px" );
			}

			// background-* props affect original clone's values
			if ( !support.clearCloneStyle && value === "" && name.indexOf( "background" ) === 0 ) {
				style[ name ] = "inherit";
			}

			// If a hook was provided, use that value, otherwise just set the specified value
			if ( !hooks || !( "set" in hooks ) ||
				( value = hooks.set( elem, value, extra ) ) !== undefined ) {

				if ( isCustomProp ) {
					style.setProperty( name, value );
				} else {
					style[ name ] = value;
				}
			}

		} else {

			// If a hook was provided get the non-computed value from there
			if ( hooks && "get" in hooks &&
				( ret = hooks.get( elem, false, extra ) ) !== undefined ) {

				return ret;
			}

			// Otherwise just get the value from the style object
			return style[ name ];
		}
	},

	css: function( elem, name, extra, styles ) {
		var val, num, hooks,
			origName = camelCase( name ),
			isCustomProp = rcustomProp.test( name );

		// Make sure that we're working with the right name. We don't
		// want to modify the value if it is a CSS custom property
		// since they are user-defined.
		if ( !isCustomProp ) {
			name = finalPropName( origName );
		}

		// Try prefixed name followed by the unprefixed name
		hooks = jQuery.cssHooks[ name ] || jQuery.cssHooks[ origName ];

		// If a hook was provided get the computed value from there
		if ( hooks && "get" in hooks ) {
			val = hooks.get( elem, true, extra );
		}

		// Otherwise, if a way to get the computed value exists, use that
		if ( val === undefined ) {
			val = curCSS( elem, name, styles );
		}

		// Convert "normal" to computed value
		if ( val === "normal" && name in cssNormalTransform ) {
			val = cssNormalTransform[ name ];
		}

		// Make numeric if forced or a qualifier was provided and val looks numeric
		if ( extra === "" || extra ) {
			num = parseFloat( val );
			return extra === true || isFinite( num ) ? num || 0 : val;
		}

		return val;
	}
} );

jQuery.each( [ "height", "width" ], function( _i, dimension ) {
	jQuery.cssHooks[ dimension ] = {
		get: function( elem, computed, extra ) {
			if ( computed ) {

				// Certain elements can have dimension info if we invisibly show them
				// but it must have a current display style that would benefit
				return rdisplayswap.test( jQuery.css( elem, "display" ) ) &&

					// Support: Safari 8+
					// Table columns in Safari have non-zero offsetWidth & zero
					// getBoundingClientRect().width unless display is changed.
					// Support: IE <=11 only
					// Running getBoundingClientRect on a disconnected node
					// in IE throws an error.
					( !elem.getClientRects().length || !elem.getBoundingClientRect().width ) ?
					swap( elem, cssShow, function() {
						return getWidthOrHeight( elem, dimension, extra );
					} ) :
					getWidthOrHeight( elem, dimension, extra );
			}
		},

		set: function( elem, value, extra ) {
			var matches,
				styles = getStyles( elem ),

				// Only read styles.position if the test has a chance to fail
				// to avoid forcing a reflow.
				scrollboxSizeBuggy = !support.scrollboxSize() &&
					styles.position === "absolute",

				// To avoid forcing a reflow, only fetch boxSizing if we need it (gh-3991)
				boxSizingNeeded = scrollboxSizeBuggy || extra,
				isBorderBox = boxSizingNeeded &&
					jQuery.css( elem, "boxSizing", false, styles ) === "border-box",
				subtract = extra ?
					boxModelAdjustment(
						elem,
						dimension,
						extra,
						isBorderBox,
						styles
					) :
					0;

			// Account for unreliable border-box dimensions by comparing offset* to computed and
			// faking a content-box to get border and padding (gh-3699)
			if ( isBorderBox && scrollboxSizeBuggy ) {
				subtract -= Math.ceil(
					elem[ "offset" + dimension[ 0 ].toUpperCase() + dimension.slice( 1 ) ] -
					parseFloat( styles[ dimension ] ) -
					boxModelAdjustment( elem, dimension, "border", false, styles ) -
					0.5
				);
			}

			// Convert to pixels if value adjustment is needed
			if ( subtract && ( matches = rcssNum.exec( value ) ) &&
				( matches[ 3 ] || "px" ) !== "px" ) {

				elem.style[ dimension ] = value;
				value = jQuery.css( elem, dimension );
			}

			return setPositiveNumber( elem, value, subtract );
		}
	};
} );

jQuery.cssHooks.marginLeft = addGetHookIf( support.reliableMarginLeft,
	function( elem, computed ) {
		if ( computed ) {
			return ( parseFloat( curCSS( elem, "marginLeft" ) ) ||
				elem.getBoundingClientRect().left -
					swap( elem, { marginLeft: 0 }, function() {
						return elem.getBoundingClientRect().left;
					} )
			) + "px";
		}
	}
);

// These hooks are used by animate to expand properties
jQuery.each( {
	margin: "",
	padding: "",
	border: "Width"
}, function( prefix, suffix ) {
	jQuery.cssHooks[ prefix + suffix ] = {
		expand: function( value ) {
			var i = 0,
				expanded = {},

				// Assumes a single number if not a string
				parts = typeof value === "string" ? value.split( " " ) : [ value ];

			for ( ; i < 4; i++ ) {
				expanded[ prefix + cssExpand[ i ] + suffix ] =
					parts[ i ] || parts[ i - 2 ] || parts[ 0 ];
			}

			return expanded;
		}
	};

	if ( prefix !== "margin" ) {
		jQuery.cssHooks[ prefix + suffix ].set = setPositiveNumber;
	}
} );

jQuery.fn.extend( {
	css: function( name, value ) {
		return access( this, function( elem, name, value ) {
			var styles, len,
				map = {},
				i = 0;

			if ( Array.isArray( name ) ) {
				styles = getStyles( elem );
				len = name.length;

				for ( ; i < len; i++ ) {
					map[ name[ i ] ] = jQuery.css( elem, name[ i ], false, styles );
				}

				return map;
			}

			return value !== undefined ?
				jQuery.style( elem, name, value ) :
				jQuery.css( elem, name );
		}, name, value, arguments.length > 1 );
	}
} );


function Tween( elem, options, prop, end, easing ) {
	return new Tween.prototype.init( elem, options, prop, end, easing );
}
jQuery.Tween = Tween;

Tween.prototype = {
	constructor: Tween,
	init: function( elem, options, prop, end, easing, unit ) {
		this.elem = elem;
		this.prop = prop;
		this.easing = easing || jQuery.easing._default;
		this.options = options;
		this.start = this.now = this.cur();
		this.end = end;
		this.unit = unit || ( jQuery.cssNumber[ prop ] ? "" : "px" );
	},
	cur: function() {
		var hooks = Tween.propHooks[ this.prop ];

		return hooks && hooks.get ?
			hooks.get( this ) :
			Tween.propHooks._default.get( this );
	},
	run: function( percent ) {
		var eased,
			hooks = Tween.propHooks[ this.prop ];

		if ( this.options.duration ) {
			this.pos = eased = jQuery.easing[ this.easing ](
				percent, this.options.duration * percent, 0, 1, this.options.duration
			);
		} else {
			this.pos = eased = percent;
		}
		this.now = ( this.end - this.start ) * eased + this.start;

		if ( this.options.step ) {
			this.options.step.call( this.elem, this.now, this );
		}

		if ( hooks && hooks.set ) {
			hooks.set( this );
		} else {
			Tween.propHooks._default.set( this );
		}
		return this;
	}
};

Tween.prototype.init.prototype = Tween.prototype;

Tween.propHooks = {
	_default: {
		get: function( tween ) {
			var result;

			// Use a property on the element directly when it is not a DOM element,
			// or when there is no matching style property that exists.
			if ( tween.elem.nodeType !== 1 ||
				tween.elem[ tween.prop ] != null && tween.elem.style[ tween.prop ] == null ) {
				return tween.elem[ tween.prop ];
			}

			// Passing an empty string as a 3rd parameter to .css will automatically
			// attempt a parseFloat and fallback to a string if the parse fails.
			// Simple values such as "10px" are parsed to Float;
			// complex values such as "rotate(1rad)" are returned as-is.
			result = jQuery.css( tween.elem, tween.prop, "" );

			// Empty strings, null, undefined and "auto" are converted to 0.
			return !result || result === "auto" ? 0 : result;
		},
		set: function( tween ) {

			// Use step hook for back compat.
			// Use cssHook if its there.
			// Use .style if available and use plain properties where available.
			if ( jQuery.fx.step[ tween.prop ] ) {
				jQuery.fx.step[ tween.prop ]( tween );
			} else if ( tween.elem.nodeType === 1 && (
				jQuery.cssHooks[ tween.prop ] ||
					tween.elem.style[ finalPropName( tween.prop ) ] != null ) ) {
				jQuery.style( tween.elem, tween.prop, tween.now + tween.unit );
			} else {
				tween.elem[ tween.prop ] = tween.now;
			}
		}
	}
};

// Support: IE <=9 only
// Panic based approach to setting things on disconnected nodes
Tween.propHooks.scrollTop = Tween.propHooks.scrollLeft = {
	set: function( tween ) {
		if ( tween.elem.nodeType && tween.elem.parentNode ) {
			tween.elem[ tween.prop ] = tween.now;
		}
	}
};

jQuery.easing = {
	linear: function( p ) {
		return p;
	},
	swing: function( p ) {
		return 0.5 - Math.cos( p * Math.PI ) / 2;
	},
	_default: "swing"
};

jQuery.fx = Tween.prototype.init;

// Back compat <1.8 extension point
jQuery.fx.step = {};




var
	fxNow, inProgress,
	rfxtypes = /^(?:toggle|show|hide)$/,
	rrun = /queueHooks$/;

function schedule() {
	if ( inProgress ) {
		if ( document.hidden === false && window.requestAnimationFrame ) {
			window.requestAnimationFrame( schedule );
		} else {
			window.setTimeout( schedule, jQuery.fx.interval );
		}

		jQuery.fx.tick();
	}
}

// Animations created synchronously will run synchronously
function createFxNow() {
	window.setTimeout( function() {
		fxNow = undefined;
	} );
	return ( fxNow = Date.now() );
}

// Generate parameters to create a standard animation
function genFx( type, includeWidth ) {
	var which,
		i = 0,
		attrs = { height: type };

	// If we include width, step value is 1 to do all cssExpand values,
	// otherwise step value is 2 to skip over Left and Right
	includeWidth = includeWidth ? 1 : 0;
	for ( ; i < 4; i += 2 - includeWidth ) {
		which = cssExpand[ i ];
		attrs[ "margin" + which ] = attrs[ "padding" + which ] = type;
	}

	if ( includeWidth ) {
		attrs.opacity = attrs.width = type;
	}

	return attrs;
}

function createTween( value, prop, animation ) {
	var tween,
		collection = ( Animation.tweeners[ prop ] || [] ).concat( Animation.tweeners[ "*" ] ),
		index = 0,
		length = collection.length;
	for ( ; index < length; index++ ) {
		if ( ( tween = collection[ index ].call( animation, prop, value ) ) ) {

			// We're done with this property
			return tween;
		}
	}
}

function defaultPrefilter( elem, props, opts ) {
	var prop, value, toggle, hooks, oldfire, propTween, restoreDisplay, display,
		isBox = "width" in props || "height" in props,
		anim = this,
		orig = {},
		style = elem.style,
		hidden = elem.nodeType && isHiddenWithinTree( elem ),
		dataShow = dataPriv.get( elem, "fxshow" );

	// Queue-skipping animations hijack the fx hooks
	if ( !opts.queue ) {
		hooks = jQuery._queueHooks( elem, "fx" );
		if ( hooks.unqueued == null ) {
			hooks.unqueued = 0;
			oldfire = hooks.empty.fire;
			hooks.empty.fire = function() {
				if ( !hooks.unqueued ) {
					oldfire();
				}
			};
		}
		hooks.unqueued++;

		anim.always( function() {

			// Ensure the complete handler is called before this completes
			anim.always( function() {
				hooks.unqueued--;
				if ( !jQuery.queue( elem, "fx" ).length ) {
					hooks.empty.fire();
				}
			} );
		} );
	}

	// Detect show/hide animations
	for ( prop in props ) {
		value = props[ prop ];
		if ( rfxtypes.test( value ) ) {
			delete props[ prop ];
			toggle = toggle || value === "toggle";
			if ( value === ( hidden ? "hide" : "show" ) ) {

				// Pretend to be hidden if this is a "show" and
				// there is still data from a stopped show/hide
				if ( value === "show" && dataShow && dataShow[ prop ] !== undefined ) {
					hidden = true;

				// Ignore all other no-op show/hide data
				} else {
					continue;
				}
			}
			orig[ prop ] = dataShow && dataShow[ prop ] || jQuery.style( elem, prop );
		}
	}

	// Bail out if this is a no-op like .hide().hide()
	propTween = !jQuery.isEmptyObject( props );
	if ( !propTween && jQuery.isEmptyObject( orig ) ) {
		return;
	}

	// Restrict "overflow" and "display" styles during box animations
	if ( isBox && elem.nodeType === 1 ) {

		// Support: IE <=9 - 11, Edge 12 - 15
		// Record all 3 overflow attributes because IE does not infer the shorthand
		// from identically-valued overflowX and overflowY and Edge just mirrors
		// the overflowX value there.
		opts.overflow = [ style.overflow, style.overflowX, style.overflowY ];

		// Identify a display type, preferring old show/hide data over the CSS cascade
		restoreDisplay = dataShow && dataShow.display;
		if ( restoreDisplay == null ) {
			restoreDisplay = dataPriv.get( elem, "display" );
		}
		display = jQuery.css( elem, "display" );
		if ( display === "none" ) {
			if ( restoreDisplay ) {
				display = restoreDisplay;
			} else {

				// Get nonempty value(s) by temporarily forcing visibility
				showHide( [ elem ], true );
				restoreDisplay = elem.style.display || restoreDisplay;
				display = jQuery.css( elem, "display" );
				showHide( [ elem ] );
			}
		}

		// Animate inline elements as inline-block
		if ( display === "inline" || display === "inline-block" && restoreDisplay != null ) {
			if ( jQuery.css( elem, "float" ) === "none" ) {

				// Restore the original display value at the end of pure show/hide animations
				if ( !propTween ) {
					anim.done( function() {
						style.display = restoreDisplay;
					} );
					if ( restoreDisplay == null ) {
						display = style.display;
						restoreDisplay = display === "none" ? "" : display;
					}
				}
				style.display = "inline-block";
			}
		}
	}

	if ( opts.overflow ) {
		style.overflow = "hidden";
		anim.always( function() {
			style.overflow = opts.overflow[ 0 ];
			style.overflowX = opts.overflow[ 1 ];
			style.overflowY = opts.overflow[ 2 ];
		} );
	}

	// Implement show/hide animations
	propTween = false;
	for ( prop in orig ) {

		// General show/hide setup for this element animation
		if ( !propTween ) {
			if ( dataShow ) {
				if ( "hidden" in dataShow ) {
					hidden = dataShow.hidden;
				}
			} else {
				dataShow = dataPriv.access( elem, "fxshow", { display: restoreDisplay } );
			}

			// Store hidden/visible for toggle so `.stop().toggle()` "reverses"
			if ( toggle ) {
				dataShow.hidden = !hidden;
			}

			// Show elements before animating them
			if ( hidden ) {
				showHide( [ elem ], true );
			}

			/* eslint-disable no-loop-func */

			anim.done( function() {

				/* eslint-enable no-loop-func */

				// The final step of a "hide" animation is actually hiding the element
				if ( !hidden ) {
					showHide( [ elem ] );
				}
				dataPriv.remove( elem, "fxshow" );
				for ( prop in orig ) {
					jQuery.style( elem, prop, orig[ prop ] );
				}
			} );
		}

		// Per-property setup
		propTween = createTween( hidden ? dataShow[ prop ] : 0, prop, anim );
		if ( !( prop in dataShow ) ) {
			dataShow[ prop ] = propTween.start;
			if ( hidden ) {
				propTween.end = propTween.start;
				propTween.start = 0;
			}
		}
	}
}

function propFilter( props, specialEasing ) {
	var index, name, easing, value, hooks;

	// camelCase, specialEasing and expand cssHook pass
	for ( index in props ) {
		name = camelCase( index );
		easing = specialEasing[ name ];
		value = props[ index ];
		if ( Array.isArray( value ) ) {
			easing = value[ 1 ];
			value = props[ index ] = value[ 0 ];
		}

		if ( index !== name ) {
			props[ name ] = value;
			delete props[ index ];
		}

		hooks = jQuery.cssHooks[ name ];
		if ( hooks && "expand" in hooks ) {
			value = hooks.expand( value );
			delete props[ name ];

			// Not quite $.extend, this won't overwrite existing keys.
			// Reusing 'index' because we have the correct "name"
			for ( index in value ) {
				if ( !( index in props ) ) {
					props[ index ] = value[ index ];
					specialEasing[ index ] = easing;
				}
			}
		} else {
			specialEasing[ name ] = easing;
		}
	}
}

function Animation( elem, properties, options ) {
	var result,
		stopped,
		index = 0,
		length = Animation.prefilters.length,
		deferred = jQuery.Deferred().always( function() {

			// Don't match elem in the :animated selector
			delete tick.elem;
		} ),
		tick = function() {
			if ( stopped ) {
				return false;
			}
			var currentTime = fxNow || createFxNow(),
				remaining = Math.max( 0, animation.startTime + animation.duration - currentTime ),

				// Support: Android 2.3 only
				// Archaic crash bug won't allow us to use `1 - ( 0.5 || 0 )` (trac-12497)
				temp = remaining / animation.duration || 0,
				percent = 1 - temp,
				index = 0,
				length = animation.tweens.length;

			for ( ; index < length; index++ ) {
				animation.tweens[ index ].run( percent );
			}

			deferred.notifyWith( elem, [ animation, percent, remaining ] );

			// If there's more to do, yield
			if ( percent < 1 && length ) {
				return remaining;
			}

			// If this was an empty animation, synthesize a final progress notification
			if ( !length ) {
				deferred.notifyWith( elem, [ animation, 1, 0 ] );
			}

			// Resolve the animation and report its conclusion
			deferred.resolveWith( elem, [ animation ] );
			return false;
		},
		animation = deferred.promise( {
			elem: elem,
			props: jQuery.extend( {}, properties ),
			opts: jQuery.extend( true, {
				specialEasing: {},
				easing: jQuery.easing._default
			}, options ),
			originalProperties: properties,
			originalOptions: options,
			startTime: fxNow || createFxNow(),
			duration: options.duration,
			tweens: [],
			createTween: function( prop, end ) {
				var tween = jQuery.Tween( elem, animation.opts, prop, end,
					animation.opts.specialEasing[ prop ] || animation.opts.easing );
				animation.tweens.push( tween );
				return tween;
			},
			stop: function( gotoEnd ) {
				var index = 0,

					// If we are going to the end, we want to run all the tweens
					// otherwise we skip this part
					length = gotoEnd ? animation.tweens.length : 0;
				if ( stopped ) {
					return this;
				}
				stopped = true;
				for ( ; index < length; index++ ) {
					animation.tweens[ index ].run( 1 );
				}

				// Resolve when we played the last frame; otherwise, reject
				if ( gotoEnd ) {
					deferred.notifyWith( elem, [ animation, 1, 0 ] );
					deferred.resolveWith( elem, [ animation, gotoEnd ] );
				} else {
					deferred.rejectWith( elem, [ animation, gotoEnd ] );
				}
				return this;
			}
		} ),
		props = animation.props;

	propFilter( props, animation.opts.specialEasing );

	for ( ; index < length; index++ ) {
		result = Animation.prefilters[ index ].call( animation, elem, props, animation.opts );
		if ( result ) {
			if ( isFunction( result.stop ) ) {
				jQuery._queueHooks( animation.elem, animation.opts.queue ).stop =
					result.stop.bind( result );
			}
			return result;
		}
	}

	jQuery.map( props, createTween, animation );

	if ( isFunction( animation.opts.start ) ) {
		animation.opts.start.call( elem, animation );
	}

	// Attach callbacks from options
	animation
		.progress( animation.opts.progress )
		.done( animation.opts.done, animation.opts.complete )
		.fail( animation.opts.fail )
		.always( animation.opts.always );

	jQuery.fx.timer(
		jQuery.extend( tick, {
			elem: elem,
			anim: animation,
			queue: animation.opts.queue
		} )
	);

	return animation;
}

jQuery.Animation = jQuery.extend( Animation, {

	tweeners: {
		"*": [ function( prop, value ) {
			var tween = this.createTween( prop, value );
			adjustCSS( tween.elem, prop, rcssNum.exec( value ), tween );
			return tween;
		} ]
	},

	tweener: function( props, callback ) {
		if ( isFunction( props ) ) {
			callback = props;
			props = [ "*" ];
		} else {
			props = props.match( rnothtmlwhite );
		}

		var prop,
			index = 0,
			length = props.length;

		for ( ; index < length; index++ ) {
			prop = props[ index ];
			Animation.tweeners[ prop ] = Animation.tweeners[ prop ] || [];
			Animation.tweeners[ prop ].unshift( callback );
		}
	},

	prefilters: [ defaultPrefilter ],

	prefilter: function( callback, prepend ) {
		if ( prepend ) {
			Animation.prefilters.unshift( callback );
		} else {
			Animation.prefilters.push( callback );
		}
	}
} );

jQuery.speed = function( speed, easing, fn ) {
	var opt = speed && typeof speed === "object" ? jQuery.extend( {}, speed ) : {
		complete: fn || !fn && easing ||
			isFunction( speed ) && speed,
		duration: speed,
		easing: fn && easing || easing && !isFunction( easing ) && easing
	};

	// Go to the end state if fx are off
	if ( jQuery.fx.off ) {
		opt.duration = 0;

	} else {
		if ( typeof opt.duration !== "number" ) {
			if ( opt.duration in jQuery.fx.speeds ) {
				opt.duration = jQuery.fx.speeds[ opt.duration ];

			} else {
				opt.duration = jQuery.fx.speeds._default;
			}
		}
	}

	// Normalize opt.queue - true/undefined/null -> "fx"
	if ( opt.queue == null || opt.queue === true ) {
		opt.queue = "fx";
	}

	// Queueing
	opt.old = opt.complete;

	opt.complete = function() {
		if ( isFunction( opt.old ) ) {
			opt.old.call( this );
		}

		if ( opt.queue ) {
			jQuery.dequeue( this, opt.queue );
		}
	};

	return opt;
};

jQuery.fn.extend( {
	fadeTo: function( speed, to, easing, callback ) {

		// Show any hidden elements after setting opacity to 0
		return this.filter( isHiddenWithinTree ).css( "opacity", 0 ).show()

			// Animate to the value specified
			.end().animate( { opacity: to }, speed, easing, callback );
	},
	animate: function( prop, speed, easing, callback ) {
		var empty = jQuery.isEmptyObject( prop ),
			optall = jQuery.speed( speed, easing, callback ),
			doAnimation = function() {

				// Operate on a copy of prop so per-property easing won't be lost
				var anim = Animation( this, jQuery.extend( {}, prop ), optall );

				// Empty animations, or finishing resolves immediately
				if ( empty || dataPriv.get( this, "finish" ) ) {
					anim.stop( true );
				}
			};

		doAnimation.finish = doAnimation;

		return empty || optall.queue === false ?
			this.each( doAnimation ) :
			this.queue( optall.queue, doAnimation );
	},
	stop: function( type, clearQueue, gotoEnd ) {
		var stopQueue = function( hooks ) {
			var stop = hooks.stop;
			delete hooks.stop;
			stop( gotoEnd );
		};

		if ( typeof type !== "string" ) {
			gotoEnd = clearQueue;
			clearQueue = type;
			type = undefined;
		}
		if ( clearQueue ) {
			this.queue( type || "fx", [] );
		}

		return this.each( function() {
			var dequeue = true,
				index = type != null && type + "queueHooks",
				timers = jQuery.timers,
				data = dataPriv.get( this );

			if ( index ) {
				if ( data[ index ] && data[ index ].stop ) {
					stopQueue( data[ index ] );
				}
			} else {
				for ( index in data ) {
					if ( data[ index ] && data[ index ].stop && rrun.test( index ) ) {
						stopQueue( data[ index ] );
					}
				}
			}

			for ( index = timers.length; index--; ) {
				if ( timers[ index ].elem === this &&
					( type == null || timers[ index ].queue === type ) ) {

					timers[ index ].anim.stop( gotoEnd );
					dequeue = false;
					timers.splice( index, 1 );
				}
			}

			// Start the next in the queue if the last step wasn't forced.
			// Timers currently will call their complete callbacks, which
			// will dequeue but only if they were gotoEnd.
			if ( dequeue || !gotoEnd ) {
				jQuery.dequeue( this, type );
			}
		} );
	},
	finish: function( type ) {
		if ( type !== false ) {
			type = type || "fx";
		}
		return this.each( function() {
			var index,
				data = dataPriv.get( this ),
				queue = data[ type + "queue" ],
				hooks = data[ type + "queueHooks" ],
				timers = jQuery.timers,
				length = queue ? queue.length : 0;

			// Enable finishing flag on private data
			data.finish = true;

			// Empty the queue first
			jQuery.queue( this, type, [] );

			if ( hooks && hooks.stop ) {
				hooks.stop.call( this, true );
			}

			// Look for any active animations, and finish them
			for ( index = timers.length; index--; ) {
				if ( timers[ index ].elem === this && timers[ index ].queue === type ) {
					timers[ index ].anim.stop( true );
					timers.splice( index, 1 );
				}
			}

			// Look for any animations in the old queue and finish them
			for ( index = 0; index < length; index++ ) {
				if ( queue[ index ] && queue[ index ].finish ) {
					queue[ index ].finish.call( this );
				}
			}

			// Turn off finishing flag
			delete data.finish;
		} );
	}
} );

jQuery.each( [ "toggle", "show", "hide" ], function( _i, name ) {
	var cssFn = jQuery.fn[ name ];
	jQuery.fn[ name ] = function( speed, easing, callback ) {
		return speed == null || typeof speed === "boolean" ?
			cssFn.apply( this, arguments ) :
			this.animate( genFx( name, true ), speed, easing, callback );
	};
} );

// Generate shortcuts for custom animations
jQuery.each( {
	slideDown: genFx( "show" ),
	slideUp: genFx( "hide" ),
	slideToggle: genFx( "toggle" ),
	fadeIn: { opacity: "show" },
	fadeOut: { opacity: "hide" },
	fadeToggle: { opacity: "toggle" }
}, function( name, props ) {
	jQuery.fn[ name ] = function( speed, easing, callback ) {
		return this.animate( props, speed, easing, callback );
	};
} );

jQuery.timers = [];
jQuery.fx.tick = function() {
	var timer,
		i = 0,
		timers = jQuery.timers;

	fxNow = Date.now();

	for ( ; i < timers.length; i++ ) {
		timer = timers[ i ];

		// Run the timer and safely remove it when done (allowing for external removal)
		if ( !timer() && timers[ i ] === timer ) {
			timers.splice( i--, 1 );
		}
	}

	if ( !timers.length ) {
		jQuery.fx.stop();
	}
	fxNow = undefined;
};

jQuery.fx.timer = function( timer ) {
	jQuery.timers.push( timer );
	jQuery.fx.start();
};

jQuery.fx.interval = 13;
jQuery.fx.start = function() {
	if ( inProgress ) {
		return;
	}

	inProgress = true;
	schedule();
};

jQuery.fx.stop = function() {
	inProgress = null;
};

jQuery.fx.speeds = {
	slow: 600,
	fast: 200,

	// Default speed
	_default: 400
};


// Based off of the plugin by Clint Helfers, with permission.
jQuery.fn.delay = function( time, type ) {
	time = jQuery.fx ? jQuery.fx.speeds[ time ] || time : time;
	type = type || "fx";

	return this.queue( type, function( next, hooks ) {
		var timeout = window.setTimeout( next, time );
		hooks.stop = function() {
			window.clearTimeout( timeout );
		};
	} );
};


( function() {
	var input = document.createElement( "input" ),
		select = document.createElement( "select" ),
		opt = select.appendChild( document.createElement( "option" ) );

	input.type = "checkbox";

	// Support: Android <=4.3 only
	// Default value for a checkbox should be "on"
	support.checkOn = input.value !== "";

	// Support: IE <=11 only
	// Must access selectedIndex to make default options select
	support.optSelected = opt.selected;

	// Support: IE <=11 only
	// An input loses its value after becoming a radio
	input = document.createElement( "input" );
	input.value = "t";
	input.type = "radio";
	support.radioValue = input.value === "t";
} )();


var boolHook,
	attrHandle = jQuery.expr.attrHandle;

jQuery.fn.extend( {
	attr: function( name, value ) {
		return access( this, jQuery.attr, name, value, arguments.length > 1 );
	},

	removeAttr: function( name ) {
		return this.each( function() {
			jQuery.removeAttr( this, name );
		} );
	}
} );

jQuery.extend( {
	attr: function( elem, name, value ) {
		var ret, hooks,
			nType = elem.nodeType;

		// Don't get/set attributes on text, comment and attribute nodes
		if ( nType === 3 || nType === 8 || nType === 2 ) {
			return;
		}

		// Fallback to prop when attributes are not supported
		if ( typeof elem.getAttribute === "undefined" ) {
			return jQuery.prop( elem, name, value );
		}

		// Attribute hooks are determined by the lowercase version
		// Grab necessary hook if one is defined
		if ( nType !== 1 || !jQuery.isXMLDoc( elem ) ) {
			hooks = jQuery.attrHooks[ name.toLowerCase() ] ||
				( jQuery.expr.match.bool.test( name ) ? boolHook : undefined );
		}

		if ( value !== undefined ) {
			if ( value === null ) {
				jQuery.removeAttr( elem, name );
				return;
			}

			if ( hooks && "set" in hooks &&
				( ret = hooks.set( elem, value, name ) ) !== undefined ) {
				return ret;
			}

			elem.setAttribute( name, value + "" );
			return value;
		}

		if ( hooks && "get" in hooks && ( ret = hooks.get( elem, name ) ) !== null ) {
			return ret;
		}

		ret = jQuery.find.attr( elem, name );

		// Non-existent attributes return null, we normalize to undefined
		return ret == null ? undefined : ret;
	},

	attrHooks: {
		type: {
			set: function( elem, value ) {
				if ( !support.radioValue && value === "radio" &&
					nodeName( elem, "input" ) ) {
					var val = elem.value;
					elem.setAttribute( "type", value );
					if ( val ) {
						elem.value = val;
					}
					return value;
				}
			}
		}
	},

	removeAttr: function( elem, value ) {
		var name,
			i = 0,

			// Attribute names can contain non-HTML whitespace characters
			// https://html.spec.whatwg.org/multipage/syntax.html#attributes-2
			attrNames = value && value.match( rnothtmlwhite );

		if ( attrNames && elem.nodeType === 1 ) {
			while ( ( name = attrNames[ i++ ] ) ) {
				elem.removeAttribute( name );
			}
		}
	}
} );

// Hooks for boolean attributes
boolHook = {
	set: function( elem, value, name ) {
		if ( value === false ) {

			// Remove boolean attributes when set to false
			jQuery.removeAttr( elem, name );
		} else {
			elem.setAttribute( name, name );
		}
		return name;
	}
};

jQuery.each( jQuery.expr.match.bool.source.match( /\w+/g ), function( _i, name ) {
	var getter = attrHandle[ name ] || jQuery.find.attr;

	attrHandle[ name ] = function( elem, name, isXML ) {
		var ret, handle,
			lowercaseName = name.toLowerCase();

		if ( !isXML ) {

			// Avoid an infinite loop by temporarily removing this function from the getter
			handle = attrHandle[ lowercaseName ];
			attrHandle[ lowercaseName ] = ret;
			ret = getter( elem, name, isXML ) != null ?
				lowercaseName :
				null;
			attrHandle[ lowercaseName ] = handle;
		}
		return ret;
	};
} );




var rfocusable = /^(?:input|select|textarea|button)$/i,
	rclickable = /^(?:a|area)$/i;

jQuery.fn.extend( {
	prop: function( name, value ) {
		return access( this, jQuery.prop, name, value, arguments.length > 1 );
	},

	removeProp: function( name ) {
		return this.each( function() {
			delete this[ jQuery.propFix[ name ] || name ];
		} );
	}
} );

jQuery.extend( {
	prop: function( elem, name, value ) {
		var ret, hooks,
			nType = elem.nodeType;

		// Don't get/set properties on text, comment and attribute nodes
		if ( nType === 3 || nType === 8 || nType === 2 ) {
			return;
		}

		if ( nType !== 1 || !jQuery.isXMLDoc( elem ) ) {

			// Fix name and attach hooks
			name = jQuery.propFix[ name ] || name;
			hooks = jQuery.propHooks[ name ];
		}

		if ( value !== undefined ) {
			if ( hooks && "set" in hooks &&
				( ret = hooks.set( elem, value, name ) ) !== undefined ) {
				return ret;
			}

			return ( elem[ name ] = value );
		}

		if ( hooks && "get" in hooks && ( ret = hooks.get( elem, name ) ) !== null ) {
			return ret;
		}

		return elem[ name ];
	},

	propHooks: {
		tabIndex: {
			get: function( elem ) {

				// Support: IE <=9 - 11 only
				// elem.tabIndex doesn't always return the
				// correct value when it hasn't been explicitly set
				// Use proper attribute retrieval (trac-12072)
				var tabindex = jQuery.find.attr( elem, "tabindex" );

				if ( tabindex ) {
					return parseInt( tabindex, 10 );
				}

				if (
					rfocusable.test( elem.nodeName ) ||
					rclickable.test( elem.nodeName ) &&
					elem.href
				) {
					return 0;
				}

				return -1;
			}
		}
	},

	propFix: {
		"for": "htmlFor",
		"class": "className"
	}
} );

// Support: IE <=11 only
// Accessing the selectedIndex property
// forces the browser to respect setting selected
// on the option
// The getter ensures a default option is selected
// when in an optgroup
// eslint rule "no-unused-expressions" is disabled for this code
// since it considers such accessions noop
if ( !support.optSelected ) {
	jQuery.propHooks.selected = {
		get: function( elem ) {

			/* eslint no-unused-expressions: "off" */

			var parent = elem.parentNode;
			if ( parent && parent.parentNode ) {
				parent.parentNode.selectedIndex;
			}
			return null;
		},
		set: function( elem ) {

			/* eslint no-unused-expressions: "off" */

			var parent = elem.parentNode;
			if ( parent ) {
				parent.selectedIndex;

				if ( parent.parentNode ) {
					parent.parentNode.selectedIndex;
				}
			}
		}
	};
}

jQuery.each( [
	"tabIndex",
	"readOnly",
	"maxLength",
	"cellSpacing",
	"cellPadding",
	"rowSpan",
	"colSpan",
	"useMap",
	"frameBorder",
	"contentEditable"
], function() {
	jQuery.propFix[ this.toLowerCase() ] = this;
} );




	// Strip and collapse whitespace according to HTML spec
	// https://infra.spec.whatwg.org/#strip-and-collapse-ascii-whitespace
	function stripAndCollapse( value ) {
		var tokens = value.match( rnothtmlwhite ) || [];
		return tokens.join( " " );
	}


function getClass( elem ) {
	return elem.getAttribute && elem.getAttribute( "class" ) || "";
}

function classesToArray( value ) {
	if ( Array.isArray( value ) ) {
		return value;
	}
	if ( typeof value === "string" ) {
		return value.match( rnothtmlwhite ) || [];
	}
	return [];
}

jQuery.fn.extend( {
	addClass: function( value ) {
		var classNames, cur, curValue, className, i, finalValue;

		if ( isFunction( value ) ) {
			return this.each( function( j ) {
				jQuery( this ).addClass( value.call( this, j, getClass( this ) ) );
			} );
		}

		classNames = classesToArray( value );

		if ( classNames.length ) {
			return this.each( function() {
				curValue = getClass( this );
				cur = this.nodeType === 1 && ( " " + stripAndCollapse( curValue ) + " " );

				if ( cur ) {
					for ( i = 0; i < classNames.length; i++ ) {
						className = classNames[ i ];
						if ( cur.indexOf( " " + className + " " ) < 0 ) {
							cur += className + " ";
						}
					}

					// Only assign if different to avoid unneeded rendering.
					finalValue = stripAndCollapse( cur );
					if ( curValue !== finalValue ) {
						this.setAttribute( "class", finalValue );
					}
				}
			} );
		}

		return this;
	},

	removeClass: function( value ) {
		var classNames, cur, curValue, className, i, finalValue;

		if ( isFunction( value ) ) {
			return this.each( function( j ) {
				jQuery( this ).removeClass( value.call( this, j, getClass( this ) ) );
			} );
		}

		if ( !arguments.length ) {
			return this.attr( "class", "" );
		}

		classNames = classesToArray( value );

		if ( classNames.length ) {
			return this.each( function() {
				curValue = getClass( this );

				// This expression is here for better compressibility (see addClass)
				cur = this.nodeType === 1 && ( " " + stripAndCollapse( curValue ) + " " );

				if ( cur ) {
					for ( i = 0; i < classNames.length; i++ ) {
						className = classNames[ i ];

						// Remove *all* instances
						while ( cur.indexOf( " " + className + " " ) > -1 ) {
							cur = cur.replace( " " + className + " ", " " );
						}
					}

					// Only assign if different to avoid unneeded rendering.
					finalValue = stripAndCollapse( cur );
					if ( curValue !== finalValue ) {
						this.setAttribute( "class", finalValue );
					}
				}
			} );
		}

		return this;
	},

	toggleClass: function( value, stateVal ) {
		var classNames, className, i, self,
			type = typeof value,
			isValidValue = type === "string" || Array.isArray( value );

		if ( isFunction( value ) ) {
			return this.each( function( i ) {
				jQuery( this ).toggleClass(
					value.call( this, i, getClass( this ), stateVal ),
					stateVal
				);
			} );
		}

		if ( typeof stateVal === "boolean" && isValidValue ) {
			return stateVal ? this.addClass( value ) : this.removeClass( value );
		}

		classNames = classesToArray( value );

		return this.each( function() {
			if ( isValidValue ) {

				// Toggle individual class names
				self = jQuery( this );

				for ( i = 0; i < classNames.length; i++ ) {
					className = classNames[ i ];

					// Check each className given, space separated list
					if ( self.hasClass( className ) ) {
						self.removeClass( className );
					} else {
						self.addClass( className );
					}
				}

			// Toggle whole class name
			} else if ( value === undefined || type === "boolean" ) {
				className = getClass( this );
				if ( className ) {

					// Store className if set
					dataPriv.set( this, "__className__", className );
				}

				// If the element has a class name or if we're passed `false`,
				// then remove the whole classname (if there was one, the above saved it).
				// Otherwise bring back whatever was previously saved (if anything),
				// falling back to the empty string if nothing was stored.
				if ( this.setAttribute ) {
					this.setAttribute( "class",
						className || value === false ?
							"" :
							dataPriv.get( this, "__className__" ) || ""
					);
				}
			}
		} );
	},

	hasClass: function( selector ) {
		var className, elem,
			i = 0;

		className = " " + selector + " ";
		while ( ( elem = this[ i++ ] ) ) {
			if ( elem.nodeType === 1 &&
				( " " + stripAndCollapse( getClass( elem ) ) + " " ).indexOf( className ) > -1 ) {
				return true;
			}
		}

		return false;
	}
} );




var rreturn = /\r/g;

jQuery.fn.extend( {
	val: function( value ) {
		var hooks, ret, valueIsFunction,
			elem = this[ 0 ];

		if ( !arguments.length ) {
			if ( elem ) {
				hooks = jQuery.valHooks[ elem.type ] ||
					jQuery.valHooks[ elem.nodeName.toLowerCase() ];

				if ( hooks &&
					"get" in hooks &&
					( ret = hooks.get( elem, "value" ) ) !== undefined
				) {
					return ret;
				}

				ret = elem.value;

				// Handle most common string cases
				if ( typeof ret === "string" ) {
					return ret.replace( rreturn, "" );
				}

				// Handle cases where value is null/undef or number
				return ret == null ? "" : ret;
			}

			return;
		}

		valueIsFunction = isFunction( value );

		return this.each( function( i ) {
			var val;

			if ( this.nodeType !== 1 ) {
				return;
			}

			if ( valueIsFunction ) {
				val = value.call( this, i, jQuery( this ).val() );
			} else {
				val = value;
			}

			// Treat null/undefined as ""; convert numbers to string
			if ( val == null ) {
				val = "";

			} else if ( typeof val === "number" ) {
				val += "";

			} else if ( Array.isArray( val ) ) {
				val = jQuery.map( val, function( value ) {
					return value == null ? "" : value + "";
				} );
			}

			hooks = jQuery.valHooks[ this.type ] || jQuery.valHooks[ this.nodeName.toLowerCase() ];

			// If set returns undefined, fall back to normal setting
			if ( !hooks || !( "set" in hooks ) || hooks.set( this, val, "value" ) === undefined ) {
				this.value = val;
			}
		} );
	}
} );

jQuery.extend( {
	valHooks: {
		option: {
			get: function( elem ) {

				var val = jQuery.find.attr( elem, "value" );
				return val != null ?
					val :

					// Support: IE <=10 - 11 only
					// option.text throws exceptions (trac-14686, trac-14858)
					// Strip and collapse whitespace
					// https://html.spec.whatwg.org/#strip-and-collapse-whitespace
					stripAndCollapse( jQuery.text( elem ) );
			}
		},
		select: {
			get: function( elem ) {
				var value, option, i,
					options = elem.options,
					index = elem.selectedIndex,
					one = elem.type === "select-one",
					values = one ? null : [],
					max = one ? index + 1 : options.length;

				if ( index < 0 ) {
					i = max;

				} else {
					i = one ? index : 0;
				}

				// Loop through all the selected options
				for ( ; i < max; i++ ) {
					option = options[ i ];

					// Support: IE <=9 only
					// IE8-9 doesn't update selected after form reset (trac-2551)
					if ( ( option.selected || i === index ) &&

							// Don't return options that are disabled or in a disabled optgroup
							!option.disabled &&
							( !option.parentNode.disabled ||
								!nodeName( option.parentNode, "optgroup" ) ) ) {

						// Get the specific value for the option
						value = jQuery( option ).val();

						// We don't need an array for one selects
						if ( one ) {
							return value;
						}

						// Multi-Selects return an array
						values.push( value );
					}
				}

				return values;
			},

			set: function( elem, value ) {
				var optionSet, option,
					options = elem.options,
					values = jQuery.makeArray( value ),
					i = options.length;

				while ( i-- ) {
					option = options[ i ];

					/* eslint-disable no-cond-assign */

					if ( option.selected =
						jQuery.inArray( jQuery.valHooks.option.get( option ), values ) > -1
					) {
						optionSet = true;
					}

					/* eslint-enable no-cond-assign */
				}

				// Force browsers to behave consistently when non-matching value is set
				if ( !optionSet ) {
					elem.selectedIndex = -1;
				}
				return values;
			}
		}
	}
} );

// Radios and checkboxes getter/setter
jQuery.each( [ "radio", "checkbox" ], function() {
	jQuery.valHooks[ this ] = {
		set: function( elem, value ) {
			if ( Array.isArray( value ) ) {
				return ( elem.checked = jQuery.inArray( jQuery( elem ).val(), value ) > -1 );
			}
		}
	};
	if ( !support.checkOn ) {
		jQuery.valHooks[ this ].get = function( elem ) {
			return elem.getAttribute( "value" ) === null ? "on" : elem.value;
		};
	}
} );




// Return jQuery for attributes-only inclusion
var location = window.location;

var nonce = { guid: Date.now() };

var rquery = ( /\?/ );



// Cross-browser xml parsing
jQuery.parseXML = function( data ) {
	var xml, parserErrorElem;
	if ( !data || typeof data !== "string" ) {
		return null;
	}

	// Support: IE 9 - 11 only
	// IE throws on parseFromString with invalid input.
	try {
		xml = ( new window.DOMParser() ).parseFromString( data, "text/xml" );
	} catch ( e ) {}

	parserErrorElem = xml && xml.getElementsByTagName( "parsererror" )[ 0 ];
	if ( !xml || parserErrorElem ) {
		jQuery.error( "Invalid XML: " + (
			parserErrorElem ?
				jQuery.map( parserErrorElem.childNodes, function( el ) {
					return el.textContent;
				} ).join( "\n" ) :
				data
		) );
	}
	return xml;
};


var rfocusMorph = /^(?:focusinfocus|focusoutblur)$/,
	stopPropagationCallback = function( e ) {
		e.stopPropagation();
	};

jQuery.extend( jQuery.event, {

	trigger: function( event, data, elem, onlyHandlers ) {

		var i, cur, tmp, bubbleType, ontype, handle, special, lastElement,
			eventPath = [ elem || document ],
			type = hasOwn.call( event, "type" ) ? event.type : event,
			namespaces = hasOwn.call( event, "namespace" ) ? event.namespace.split( "." ) : [];

		cur = lastElement = tmp = elem = elem || document;

		// Don't do events on text and comment nodes
		if ( elem.nodeType === 3 || elem.nodeType === 8 ) {
			return;
		}

		// focus/blur morphs to focusin/out; ensure we're not firing them right now
		if ( rfocusMorph.test( type + jQuery.event.triggered ) ) {
			return;
		}

		if ( type.indexOf( "." ) > -1 ) {

			// Namespaced trigger; create a regexp to match event type in handle()
			namespaces = type.split( "." );
			type = namespaces.shift();
			namespaces.sort();
		}
		ontype = type.indexOf( ":" ) < 0 && "on" + type;

		// Caller can pass in a jQuery.Event object, Object, or just an event type string
		event = event[ jQuery.expando ] ?
			event :
			new jQuery.Event( type, typeof event === "object" && event );

		// Trigger bitmask: & 1 for native handlers; & 2 for jQuery (always true)
		event.isTrigger = onlyHandlers ? 2 : 3;
		event.namespace = namespaces.join( "." );
		event.rnamespace = event.namespace ?
			new RegExp( "(^|\\.)" + namespaces.join( "\\.(?:.*\\.|)" ) + "(\\.|$)" ) :
			null;

		// Clean up the event in case it is being reused
		event.result = undefined;
		if ( !event.target ) {
			event.target = elem;
		}

		// Clone any incoming data and prepend the event, creating the handler arg list
		data = data == null ?
			[ event ] :
			jQuery.makeArray( data, [ event ] );

		// Allow special events to draw outside the lines
		special = jQuery.event.special[ type ] || {};
		if ( !onlyHandlers && special.trigger && special.trigger.apply( elem, data ) === false ) {
			return;
		}

		// Determine event propagation path in advance, per W3C events spec (trac-9951)
		// Bubble up to document, then to window; watch for a global ownerDocument var (trac-9724)
		if ( !onlyHandlers && !special.noBubble && !isWindow( elem ) ) {

			bubbleType = special.delegateType || type;
			if ( !rfocusMorph.test( bubbleType + type ) ) {
				cur = cur.parentNode;
			}
			for ( ; cur; cur = cur.parentNode ) {
				eventPath.push( cur );
				tmp = cur;
			}

			// Only add window if we got to document (e.g., not plain obj or detached DOM)
			if ( tmp === ( elem.ownerDocument || document ) ) {
				eventPath.push( tmp.defaultView || tmp.parentWindow || window );
			}
		}

		// Fire handlers on the event path
		i = 0;
		while ( ( cur = eventPath[ i++ ] ) && !event.isPropagationStopped() ) {
			lastElement = cur;
			event.type = i > 1 ?
				bubbleType :
				special.bindType || type;

			// jQuery handler
			handle = ( dataPriv.get( cur, "events" ) || Object.create( null ) )[ event.type ] &&
				dataPriv.get( cur, "handle" );
			if ( handle ) {
				handle.apply( cur, data );
			}

			// Native handler
			handle = ontype && cur[ ontype ];
			if ( handle && handle.apply && acceptData( cur ) ) {
				event.result = handle.apply( cur, data );
				if ( event.result === false ) {
					event.preventDefault();
				}
			}
		}
		event.type = type;

		// If nobody prevented the default action, do it now
		if ( !onlyHandlers && !event.isDefaultPrevented() ) {

			if ( ( !special._default ||
				special._default.apply( eventPath.pop(), data ) === false ) &&
				acceptData( elem ) ) {

				// Call a native DOM method on the target with the same name as the event.
				// Don't do default actions on window, that's where global variables be (trac-6170)
				if ( ontype && isFunction( elem[ type ] ) && !isWindow( elem ) ) {

					// Don't re-trigger an onFOO event when we call its FOO() method
					tmp = elem[ ontype ];

					if ( tmp ) {
						elem[ ontype ] = null;
					}

					// Prevent re-triggering of the same event, since we already bubbled it above
					jQuery.event.triggered = type;

					if ( event.isPropagationStopped() ) {
						lastElement.addEventListener( type, stopPropagationCallback );
					}

					elem[ type ]();

					if ( event.isPropagationStopped() ) {
						lastElement.removeEventListener( type, stopPropagationCallback );
					}

					jQuery.event.triggered = undefined;

					if ( tmp ) {
						elem[ ontype ] = tmp;
					}
				}
			}
		}

		return event.result;
	},

	// Piggyback on a donor event to simulate a different one
	// Used only for `focus(in | out)` events
	simulate: function( type, elem, event ) {
		var e = jQuery.extend(
			new jQuery.Event(),
			event,
			{
				type: type,
				isSimulated: true
			}
		);

		jQuery.event.trigger( e, null, elem );
	}

} );

jQuery.fn.extend( {

	trigger: function( type, data ) {
		return this.each( function() {
			jQuery.event.trigger( type, data, this );
		} );
	},
	triggerHandler: function( type, data ) {
		var elem = this[ 0 ];
		if ( elem ) {
			return jQuery.event.trigger( type, data, elem, true );
		}
	}
} );


var
	rbracket = /\[\]$/,
	rCRLF = /\r?\n/g,
	rsubmitterTypes = /^(?:submit|button|image|reset|file)$/i,
	rsubmittable = /^(?:input|select|textarea|keygen)/i;

function buildParams( prefix, obj, traditional, add ) {
	var name;

	if ( Array.isArray( obj ) ) {

		// Serialize array item.
		jQuery.each( obj, function( i, v ) {
			if ( traditional || rbracket.test( prefix ) ) {

				// Treat each array item as a scalar.
				add( prefix, v );

			} else {

				// Item is non-scalar (array or object), encode its numeric index.
				buildParams(
					prefix + "[" + ( typeof v === "object" && v != null ? i : "" ) + "]",
					v,
					traditional,
					add
				);
			}
		} );

	} else if ( !traditional && toType( obj ) === "object" ) {

		// Serialize object item.
		for ( name in obj ) {
			buildParams( prefix + "[" + name + "]", obj[ name ], traditional, add );
		}

	} else {

		// Serialize scalar item.
		add( prefix, obj );
	}
}

// Serialize an array of form elements or a set of
// key/values into a query string
jQuery.param = function( a, traditional ) {
	var prefix,
		s = [],
		add = function( key, valueOrFunction ) {

			// If value is a function, invoke it and use its return value
			var value = isFunction( valueOrFunction ) ?
				valueOrFunction() :
				valueOrFunction;

			s[ s.length ] = encodeURIComponent( key ) + "=" +
				encodeURIComponent( value == null ? "" : value );
		};

	if ( a == null ) {
		return "";
	}

	// If an array was passed in, assume that it is an array of form elements.
	if ( Array.isArray( a ) || ( a.jquery && !jQuery.isPlainObject( a ) ) ) {

		// Serialize the form elements
		jQuery.each( a, function() {
			add( this.name, this.value );
		} );

	} else {

		// If traditional, encode the "old" way (the way 1.3.2 or older
		// did it), otherwise encode params recursively.
		for ( prefix in a ) {
			buildParams( prefix, a[ prefix ], traditional, add );
		}
	}

	// Return the resulting serialization
	return s.join( "&" );
};

jQuery.fn.extend( {
	serialize: function() {
		return jQuery.param( this.serializeArray() );
	},
	serializeArray: function() {
		return this.map( function() {

			// Can add propHook for "elements" to filter or add form elements
			var elements = jQuery.prop( this, "elements" );
			return elements ? jQuery.makeArray( elements ) : this;
		} ).filter( function() {
			var type = this.type;

			// Use .is( ":disabled" ) so that fieldset[disabled] works
			return this.name && !jQuery( this ).is( ":disabled" ) &&
				rsubmittable.test( this.nodeName ) && !rsubmitterTypes.test( type ) &&
				( this.checked || !rcheckableType.test( type ) );
		} ).map( function( _i, elem ) {
			var val = jQuery( this ).val();

			if ( val == null ) {
				return null;
			}

			if ( Array.isArray( val ) ) {
				return jQuery.map( val, function( val ) {
					return { name: elem.name, value: val.replace( rCRLF, "\r\n" ) };
				} );
			}

			return { name: elem.name, value: val.replace( rCRLF, "\r\n" ) };
		} ).get();
	}
} );


var
	r20 = /%20/g,
	rhash = /#.*$/,
	rantiCache = /([?&])_=[^&]*/,
	rheaders = /^(.*?):[ \t]*([^\r\n]*)$/mg,

	// trac-7653, trac-8125, trac-8152: local protocol detection
	rlocalProtocol = /^(?:about|app|app-storage|.+-extension|file|res|widget):$/,
	rnoContent = /^(?:GET|HEAD)$/,
	rprotocol = /^\/\//,

	/* Prefilters
	 * 1) They are useful to introduce custom dataTypes (see ajax/jsonp.js for an example)
	 * 2) These are called:
	 *    - BEFORE asking for a transport
	 *    - AFTER param serialization (s.data is a string if s.processData is true)
	 * 3) key is the dataType
	 * 4) the catchall symbol "*" can be used
	 * 5) execution will start with transport dataType and THEN continue down to "*" if needed
	 */
	prefilters = {},

	/* Transports bindings
	 * 1) key is the dataType
	 * 2) the catchall symbol "*" can be used
	 * 3) selection will start with transport dataType and THEN go to "*" if needed
	 */
	transports = {},

	// Avoid comment-prolog char sequence (trac-10098); must appease lint and evade compression
	allTypes = "*/".concat( "*" ),

	// Anchor tag for parsing the document origin
	originAnchor = document.createElement( "a" );

originAnchor.href = location.href;

// Base "constructor" for jQuery.ajaxPrefilter and jQuery.ajaxTransport
function addToPrefiltersOrTransports( structure ) {

	// dataTypeExpression is optional and defaults to "*"
	return function( dataTypeExpression, func ) {

		if ( typeof dataTypeExpression !== "string" ) {
			func = dataTypeExpression;
			dataTypeExpression = "*";
		}

		var dataType,
			i = 0,
			dataTypes = dataTypeExpression.toLowerCase().match( rnothtmlwhite ) || [];

		if ( isFunction( func ) ) {

			// For each dataType in the dataTypeExpression
			while ( ( dataType = dataTypes[ i++ ] ) ) {

				// Prepend if requested
				if ( dataType[ 0 ] === "+" ) {
					dataType = dataType.slice( 1 ) || "*";
					( structure[ dataType ] = structure[ dataType ] || [] ).unshift( func );

				// Otherwise append
				} else {
					( structure[ dataType ] = structure[ dataType ] || [] ).push( func );
				}
			}
		}
	};
}

// Base inspection function for prefilters and transports
function inspectPrefiltersOrTransports( structure, options, originalOptions, jqXHR ) {

	var inspected = {},
		seekingTransport = ( structure === transports );

	function inspect( dataType ) {
		var selected;
		inspected[ dataType ] = true;
		jQuery.each( structure[ dataType ] || [], function( _, prefilterOrFactory ) {
			var dataTypeOrTransport = prefilterOrFactory( options, originalOptions, jqXHR );
			if ( typeof dataTypeOrTransport === "string" &&
				!seekingTransport && !inspected[ dataTypeOrTransport ] ) {

				options.dataTypes.unshift( dataTypeOrTransport );
				inspect( dataTypeOrTransport );
				return false;
			} else if ( seekingTransport ) {
				return !( selected = dataTypeOrTransport );
			}
		} );
		return selected;
	}

	return inspect( options.dataTypes[ 0 ] ) || !inspected[ "*" ] && inspect( "*" );
}

// A special extend for ajax options
// that takes "flat" options (not to be deep extended)
// Fixes trac-9887
function ajaxExtend( target, src ) {
	var key, deep,
		flatOptions = jQuery.ajaxSettings.flatOptions || {};

	for ( key in src ) {
		if ( src[ key ] !== undefined ) {
			( flatOptions[ key ] ? target : ( deep || ( deep = {} ) ) )[ key ] = src[ key ];
		}
	}
	if ( deep ) {
		jQuery.extend( true, target, deep );
	}

	return target;
}

/* Handles responses to an ajax request:
 * - finds the right dataType (mediates between content-type and expected dataType)
 * - returns the corresponding response
 */
function ajaxHandleResponses( s, jqXHR, responses ) {

	var ct, type, finalDataType, firstDataType,
		contents = s.contents,
		dataTypes = s.dataTypes;

	// Remove auto dataType and get content-type in the process
	while ( dataTypes[ 0 ] === "*" ) {
		dataTypes.shift();
		if ( ct === undefined ) {
			ct = s.mimeType || jqXHR.getResponseHeader( "Content-Type" );
		}
	}

	// Check if we're dealing with a known content-type
	if ( ct ) {
		for ( type in contents ) {
			if ( contents[ type ] && contents[ type ].test( ct ) ) {
				dataTypes.unshift( type );
				break;
			}
		}
	}

	// Check to see if we have a response for the expected dataType
	if ( dataTypes[ 0 ] in responses ) {
		finalDataType = dataTypes[ 0 ];
	} else {

		// Try convertible dataTypes
		for ( type in responses ) {
			if ( !dataTypes[ 0 ] || s.converters[ type + " " + dataTypes[ 0 ] ] ) {
				finalDataType = type;
				break;
			}
			if ( !firstDataType ) {
				firstDataType = type;
			}
		}

		// Or just use first one
		finalDataType = finalDataType || firstDataType;
	}

	// If we found a dataType
	// We add the dataType to the list if needed
	// and return the corresponding response
	if ( finalDataType ) {
		if ( finalDataType !== dataTypes[ 0 ] ) {
			dataTypes.unshift( finalDataType );
		}
		return responses[ finalDataType ];
	}
}

/* Chain conversions given the request and the original response
 * Also sets the responseXXX fields on the jqXHR instance
 */
function ajaxConvert( s, response, jqXHR, isSuccess ) {
	var conv2, current, conv, tmp, prev,
		converters = {},

		// Work with a copy of dataTypes in case we need to modify it for conversion
		dataTypes = s.dataTypes.slice();

	// Create converters map with lowercased keys
	if ( dataTypes[ 1 ] ) {
		for ( conv in s.converters ) {
			converters[ conv.toLowerCase() ] = s.converters[ conv ];
		}
	}

	current = dataTypes.shift();

	// Convert to each sequential dataType
	while ( current ) {

		if ( s.responseFields[ current ] ) {
			jqXHR[ s.responseFields[ current ] ] = response;
		}

		// Apply the dataFilter if provided
		if ( !prev && isSuccess && s.dataFilter ) {
			response = s.dataFilter( response, s.dataType );
		}

		prev = current;
		current = dataTypes.shift();

		if ( current ) {

			// There's only work to do if current dataType is non-auto
			if ( current === "*" ) {

				current = prev;

			// Convert response if prev dataType is non-auto and differs from current
			} else if ( prev !== "*" && prev !== current ) {

				// Seek a direct converter
				conv = converters[ prev + " " + current ] || converters[ "* " + current ];

				// If none found, seek a pair
				if ( !conv ) {
					for ( conv2 in converters ) {

						// If conv2 outputs current
						tmp = conv2.split( " " );
						if ( tmp[ 1 ] === current ) {

							// If prev can be converted to accepted input
							conv = converters[ prev + " " + tmp[ 0 ] ] ||
								converters[ "* " + tmp[ 0 ] ];
							if ( conv ) {

								// Condense equivalence converters
								if ( conv === true ) {
									conv = converters[ conv2 ];

								// Otherwise, insert the intermediate dataType
								} else if ( converters[ conv2 ] !== true ) {
									current = tmp[ 0 ];
									dataTypes.unshift( tmp[ 1 ] );
								}
								break;
							}
						}
					}
				}

				// Apply converter (if not an equivalence)
				if ( conv !== true ) {

					// Unless errors are allowed to bubble, catch and return them
					if ( conv && s.throws ) {
						response = conv( response );
					} else {
						try {
							response = conv( response );
						} catch ( e ) {
							return {
								state: "parsererror",
								error: conv ? e : "No conversion from " + prev + " to " + current
							};
						}
					}
				}
			}
		}
	}

	return { state: "success", data: response };
}

jQuery.extend( {

	// Counter for holding the number of active queries
	active: 0,

	// Last-Modified header cache for next request
	lastModified: {},
	etag: {},

	ajaxSettings: {
		url: location.href,
		type: "GET",
		isLocal: rlocalProtocol.test( location.protocol ),
		global: true,
		processData: true,
		async: true,
		contentType: "application/x-www-form-urlencoded; charset=UTF-8",

		/*
		timeout: 0,
		data: null,
		dataType: null,
		username: null,
		password: null,
		cache: null,
		throws: false,
		traditional: false,
		headers: {},
		*/

		accepts: {
			"*": allTypes,
			text: "text/plain",
			html: "text/html",
			xml: "application/xml, text/xml",
			json: "application/json, text/javascript"
		},

		contents: {
			xml: /\bxml\b/,
			html: /\bhtml/,
			json: /\bjson\b/
		},

		responseFields: {
			xml: "responseXML",
			text: "responseText",
			json: "responseJSON"
		},

		// Data converters
		// Keys separate source (or catchall "*") and destination types with a single space
		converters: {

			// Convert anything to text
			"* text": String,

			// Text to html (true = no transformation)
			"text html": true,

			// Evaluate text as a json expression
			"text json": JSON.parse,

			// Parse text as xml
			"text xml": jQuery.parseXML
		},

		// For options that shouldn't be deep extended:
		// you can add your own custom options here if
		// and when you create one that shouldn't be
		// deep extended (see ajaxExtend)
		flatOptions: {
			url: true,
			context: true
		}
	},

	// Creates a full fledged settings object into target
	// with both ajaxSettings and settings fields.
	// If target is omitted, writes into ajaxSettings.
	ajaxSetup: function( target, settings ) {
		return settings ?

			// Building a settings object
			ajaxExtend( ajaxExtend( target, jQuery.ajaxSettings ), settings ) :

			// Extending ajaxSettings
			ajaxExtend( jQuery.ajaxSettings, target );
	},

	ajaxPrefilter: addToPrefiltersOrTransports( prefilters ),
	ajaxTransport: addToPrefiltersOrTransports( transports ),

	// Main method
	ajax: function( url, options ) {

		// If url is an object, simulate pre-1.5 signature
		if ( typeof url === "object" ) {
			options = url;
			url = undefined;
		}

		// Force options to be an object
		options = options || {};

		var transport,

			// URL without anti-cache param
			cacheURL,

			// Response headers
			responseHeadersString,
			responseHeaders,

			// timeout handle
			timeoutTimer,

			// Url cleanup var
			urlAnchor,

			// Request state (becomes false upon send and true upon completion)
			completed,

			// To know if global events are to be dispatched
			fireGlobals,

			// Loop variable
			i,

			// uncached part of the url
			uncached,

			// Create the final options object
			s = jQuery.ajaxSetup( {}, options ),

			// Callbacks context
			callbackContext = s.context || s,

			// Context for global events is callbackContext if it is a DOM node or jQuery collection
			globalEventContext = s.context &&
				( callbackContext.nodeType || callbackContext.jquery ) ?
				jQuery( callbackContext ) :
				jQuery.event,

			// Deferreds
			deferred = jQuery.Deferred(),
			completeDeferred = jQuery.Callbacks( "once memory" ),

			// Status-dependent callbacks
			statusCode = s.statusCode || {},

			// Headers (they are sent all at once)
			requestHeaders = {},
			requestHeadersNames = {},

			// Default abort message
			strAbort = "canceled",

			// Fake xhr
			jqXHR = {
				readyState: 0,

				// Builds headers hashtable if needed
				getResponseHeader: function( key ) {
					var match;
					if ( completed ) {
						if ( !responseHeaders ) {
							responseHeaders = {};
							while ( ( match = rheaders.exec( responseHeadersString ) ) ) {
								responseHeaders[ match[ 1 ].toLowerCase() + " " ] =
									( responseHeaders[ match[ 1 ].toLowerCase() + " " ] || [] )
										.concat( match[ 2 ] );
							}
						}
						match = responseHeaders[ key.toLowerCase() + " " ];
					}
					return match == null ? null : match.join( ", " );
				},

				// Raw string
				getAllResponseHeaders: function() {
					return completed ? responseHeadersString : null;
				},

				// Caches the header
				setRequestHeader: function( name, value ) {
					if ( completed == null ) {
						name = requestHeadersNames[ name.toLowerCase() ] =
							requestHeadersNames[ name.toLowerCase() ] || name;
						requestHeaders[ name ] = value;
					}
					return this;
				},

				// Overrides response content-type header
				overrideMimeType: function( type ) {
					if ( completed == null ) {
						s.mimeType = type;
					}
					return this;
				},

				// Status-dependent callbacks
				statusCode: function( map ) {
					var code;
					if ( map ) {
						if ( completed ) {

							// Execute the appropriate callbacks
							jqXHR.always( map[ jqXHR.status ] );
						} else {

							// Lazy-add the new callbacks in a way that preserves old ones
							for ( code in map ) {
								statusCode[ code ] = [ statusCode[ code ], map[ code ] ];
							}
						}
					}
					return this;
				},

				// Cancel the request
				abort: function( statusText ) {
					var finalText = statusText || strAbort;
					if ( transport ) {
						transport.abort( finalText );
					}
					done( 0, finalText );
					return this;
				}
			};

		// Attach deferreds
		deferred.promise( jqXHR );

		// Add protocol if not provided (prefilters might expect it)
		// Handle falsy url in the settings object (trac-10093: consistency with old signature)
		// We also use the url parameter if available
		s.url = ( ( url || s.url || location.href ) + "" )
			.replace( rprotocol, location.protocol + "//" );

		// Alias method option to type as per ticket trac-12004
		s.type = options.method || options.type || s.method || s.type;

		// Extract dataTypes list
		s.dataTypes = ( s.dataType || "*" ).toLowerCase().match( rnothtmlwhite ) || [ "" ];

		// A cross-domain request is in order when the origin doesn't match the current origin.
		if ( s.crossDomain == null ) {
			urlAnchor = document.createElement( "a" );

			// Support: IE <=8 - 11, Edge 12 - 15
			// IE throws exception on accessing the href property if url is malformed,
			// e.g. http://example.com:80x/
			try {
				urlAnchor.href = s.url;

				// Support: IE <=8 - 11 only
				// Anchor's host property isn't correctly set when s.url is relative
				urlAnchor.href = urlAnchor.href;
				s.crossDomain = originAnchor.protocol + "//" + originAnchor.host !==
					urlAnchor.protocol + "//" + urlAnchor.host;
			} catch ( e ) {

				// If there is an error parsing the URL, assume it is crossDomain,
				// it can be rejected by the transport if it is invalid
				s.crossDomain = true;
			}
		}

		// Convert data if not already a string
		if ( s.data && s.processData && typeof s.data !== "string" ) {
			s.data = jQuery.param( s.data, s.traditional );
		}

		// Apply prefilters
		inspectPrefiltersOrTransports( prefilters, s, options, jqXHR );

		// If request was aborted inside a prefilter, stop there
		if ( completed ) {
			return jqXHR;
		}

		// We can fire global events as of now if asked to
		// Don't fire events if jQuery.event is undefined in an AMD-usage scenario (trac-15118)
		fireGlobals = jQuery.event && s.global;

		// Watch for a new set of requests
		if ( fireGlobals && jQuery.active++ === 0 ) {
			jQuery.event.trigger( "ajaxStart" );
		}

		// Uppercase the type
		s.type = s.type.toUpperCase();

		// Determine if request has content
		s.hasContent = !rnoContent.test( s.type );

		// Save the URL in case we're toying with the If-Modified-Since
		// and/or If-None-Match header later on
		// Remove hash to simplify url manipulation
		cacheURL = s.url.replace( rhash, "" );

		// More options handling for requests with no content
		if ( !s.hasContent ) {

			// Remember the hash so we can put it back
			uncached = s.url.slice( cacheURL.length );

			// If data is available and should be processed, append data to url
			if ( s.data && ( s.processData || typeof s.data === "string" ) ) {
				cacheURL += ( rquery.test( cacheURL ) ? "&" : "?" ) + s.data;

				// trac-9682: remove data so that it's not used in an eventual retry
				delete s.data;
			}

			// Add or update anti-cache param if needed
			if ( s.cache === false ) {
				cacheURL = cacheURL.replace( rantiCache, "$1" );
				uncached = ( rquery.test( cacheURL ) ? "&" : "?" ) + "_=" + ( nonce.guid++ ) +
					uncached;
			}

			// Put hash and anti-cache on the URL that will be requested (gh-1732)
			s.url = cacheURL + uncached;

		// Change '%20' to '+' if this is encoded form body content (gh-2658)
		} else if ( s.data && s.processData &&
			( s.contentType || "" ).indexOf( "application/x-www-form-urlencoded" ) === 0 ) {
			s.data = s.data.replace( r20, "+" );
		}

		// Set the If-Modified-Since and/or If-None-Match header, if in ifModified mode.
		if ( s.ifModified ) {
			if ( jQuery.lastModified[ cacheURL ] ) {
				jqXHR.setRequestHeader( "If-Modified-Since", jQuery.lastModified[ cacheURL ] );
			}
			if ( jQuery.etag[ cacheURL ] ) {
				jqXHR.setRequestHeader( "If-None-Match", jQuery.etag[ cacheURL ] );
			}
		}

		// Set the correct header, if data is being sent
		if ( s.data && s.hasContent && s.contentType !== false || options.contentType ) {
			jqXHR.setRequestHeader( "Content-Type", s.contentType );
		}

		// Set the Accepts header for the server, depending on the dataType
		jqXHR.setRequestHeader(
			"Accept",
			s.dataTypes[ 0 ] && s.accepts[ s.dataTypes[ 0 ] ] ?
				s.accepts[ s.dataTypes[ 0 ] ] +
					( s.dataTypes[ 0 ] !== "*" ? ", " + allTypes + "; q=0.01" : "" ) :
				s.accepts[ "*" ]
		);

		// Check for headers option
		for ( i in s.headers ) {
			jqXHR.setRequestHeader( i, s.headers[ i ] );
		}

		// Allow custom headers/mimetypes and early abort
		if ( s.beforeSend &&
			( s.beforeSend.call( callbackContext, jqXHR, s ) === false || completed ) ) {

			// Abort if not done already and return
			return jqXHR.abort();
		}

		// Aborting is no longer a cancellation
		strAbort = "abort";

		// Install callbacks on deferreds
		completeDeferred.add( s.complete );
		jqXHR.done( s.success );
		jqXHR.fail( s.error );

		// Get transport
		transport = inspectPrefiltersOrTransports( transports, s, options, jqXHR );

		// If no transport, we auto-abort
		if ( !transport ) {
			done( -1, "No Transport" );
		} else {
			jqXHR.readyState = 1;

			// Send global event
			if ( fireGlobals ) {
				globalEventContext.trigger( "ajaxSend", [ jqXHR, s ] );
			}

			// If request was aborted inside ajaxSend, stop there
			if ( completed ) {
				return jqXHR;
			}

			// Timeout
			if ( s.async && s.timeout > 0 ) {
				timeoutTimer = window.setTimeout( function() {
					jqXHR.abort( "timeout" );
				}, s.timeout );
			}

			try {
				completed = false;
				transport.send( requestHeaders, done );
			} catch ( e ) {

				// Rethrow post-completion exceptions
				if ( completed ) {
					throw e;
				}

				// Propagate others as results
				done( -1, e );
			}
		}

		// Callback for when everything is done
		function done( status, nativeStatusText, responses, headers ) {
			var isSuccess, success, error, response, modified,
				statusText = nativeStatusText;

			// Ignore repeat invocations
			if ( completed ) {
				return;
			}

			completed = true;

			// Clear timeout if it exists
			if ( timeoutTimer ) {
				window.clearTimeout( timeoutTimer );
			}

			// Dereference transport for early garbage collection
			// (no matter how long the jqXHR object will be used)
			transport = undefined;

			// Cache response headers
			responseHeadersString = headers || "";

			// Set readyState
			jqXHR.readyState = status > 0 ? 4 : 0;

			// Determine if successful
			isSuccess = status >= 200 && status < 300 || status === 304;

			// Get response data
			if ( responses ) {
				response = ajaxHandleResponses( s, jqXHR, responses );
			}

			// Use a noop converter for missing script but not if jsonp
			if ( !isSuccess &&
				jQuery.inArray( "script", s.dataTypes ) > -1 &&
				jQuery.inArray( "json", s.dataTypes ) < 0 ) {
				s.converters[ "text script" ] = function() {};
			}

			// Convert no matter what (that way responseXXX fields are always set)
			response = ajaxConvert( s, response, jqXHR, isSuccess );

			// If successful, handle type chaining
			if ( isSuccess ) {

				// Set the If-Modified-Since and/or If-None-Match header, if in ifModified mode.
				if ( s.ifModified ) {
					modified = jqXHR.getResponseHeader( "Last-Modified" );
					if ( modified ) {
						jQuery.lastModified[ cacheURL ] = modified;
					}
					modified = jqXHR.getResponseHeader( "etag" );
					if ( modified ) {
						jQuery.etag[ cacheURL ] = modified;
					}
				}

				// if no content
				if ( status === 204 || s.type === "HEAD" ) {
					statusText = "nocontent";

				// if not modified
				} else if ( status === 304 ) {
					statusText = "notmodified";

				// If we have data, let's convert it
				} else {
					statusText = response.state;
					success = response.data;
					error = response.error;
					isSuccess = !error;
				}
			} else {

				// Extract error from statusText and normalize for non-aborts
				error = statusText;
				if ( status || !statusText ) {
					statusText = "error";
					if ( status < 0 ) {
						status = 0;
					}
				}
			}

			// Set data for the fake xhr object
			jqXHR.status = status;
			jqXHR.statusText = ( nativeStatusText || statusText ) + "";

			// Success/Error
			if ( isSuccess ) {
				deferred.resolveWith( callbackContext, [ success, statusText, jqXHR ] );
			} else {
				deferred.rejectWith( callbackContext, [ jqXHR, statusText, error ] );
			}

			// Status-dependent callbacks
			jqXHR.statusCode( statusCode );
			statusCode = undefined;

			if ( fireGlobals ) {
				globalEventContext.trigger( isSuccess ? "ajaxSuccess" : "ajaxError",
					[ jqXHR, s, isSuccess ? success : error ] );
			}

			// Complete
			completeDeferred.fireWith( callbackContext, [ jqXHR, statusText ] );

			if ( fireGlobals ) {
				globalEventContext.trigger( "ajaxComplete", [ jqXHR, s ] );

				// Handle the global AJAX counter
				if ( !( --jQuery.active ) ) {
					jQuery.event.trigger( "ajaxStop" );
				}
			}
		}

		return jqXHR;
	},

	getJSON: function( url, data, callback ) {
		return jQuery.get( url, data, callback, "json" );
	},

	getScript: function( url, callback ) {
		return jQuery.get( url, undefined, callback, "script" );
	}
} );

jQuery.each( [ "get", "post" ], function( _i, method ) {
	jQuery[ method ] = function( url, data, callback, type ) {

		// Shift arguments if data argument was omitted
		if ( isFunction( data ) ) {
			type = type || callback;
			callback = data;
			data = undefined;
		}

		// The url can be an options object (which then must have .url)
		return jQuery.ajax( jQuery.extend( {
			url: url,
			type: method,
			dataType: type,
			data: data,
			success: callback
		}, jQuery.isPlainObject( url ) && url ) );
	};
} );

jQuery.ajaxPrefilter( function( s ) {
	var i;
	for ( i in s.headers ) {
		if ( i.toLowerCase() === "content-type" ) {
			s.contentType = s.headers[ i ] || "";
		}
	}
} );


jQuery._evalUrl = function( url, options, doc ) {
	return jQuery.ajax( {
		url: url,

		// Make this explicit, since user can override this through ajaxSetup (trac-11264)
		type: "GET",
		dataType: "script",
		cache: true,
		async: false,
		global: false,

		// Only evaluate the response if it is successful (gh-4126)
		// dataFilter is not invoked for failure responses, so using it instead
		// of the default converter is kludgy but it works.
		converters: {
			"text script": function() {}
		},
		dataFilter: function( response ) {
			jQuery.globalEval( response, options, doc );
		}
	} );
};


jQuery.fn.extend( {
	wrapAll: function( html ) {
		var wrap;

		if ( this[ 0 ] ) {
			if ( isFunction( html ) ) {
				html = html.call( this[ 0 ] );
			}

			// The elements to wrap the target around
			wrap = jQuery( html, this[ 0 ].ownerDocument ).eq( 0 ).clone( true );

			if ( this[ 0 ].parentNode ) {
				wrap.insertBefore( this[ 0 ] );
			}

			wrap.map( function() {
				var elem = this;

				while ( elem.firstElementChild ) {
					elem = elem.firstElementChild;
				}

				return elem;
			} ).append( this );
		}

		return this;
	},

	wrapInner: function( html ) {
		if ( isFunction( html ) ) {
			return this.each( function( i ) {
				jQuery( this ).wrapInner( html.call( this, i ) );
			} );
		}

		return this.each( function() {
			var self = jQuery( this ),
				contents = self.contents();

			if ( contents.length ) {
				contents.wrapAll( html );

			} else {
				self.append( html );
			}
		} );
	},

	wrap: function( html ) {
		var htmlIsFunction = isFunction( html );

		return this.each( function( i ) {
			jQuery( this ).wrapAll( htmlIsFunction ? html.call( this, i ) : html );
		} );
	},

	unwrap: function( selector ) {
		this.parent( selector ).not( "body" ).each( function() {
			jQuery( this ).replaceWith( this.childNodes );
		} );
		return this;
	}
} );


jQuery.expr.pseudos.hidden = function( elem ) {
	return !jQuery.expr.pseudos.visible( elem );
};
jQuery.expr.pseudos.visible = function( elem ) {
	return !!( elem.offsetWidth || elem.offsetHeight || elem.getClientRects().length );
};




jQuery.ajaxSettings.xhr = function() {
	try {
		return new window.XMLHttpRequest();
	} catch ( e ) {}
};

var xhrSuccessStatus = {

		// File protocol always yields status code 0, assume 200
		0: 200,

		// Support: IE <=9 only
		// trac-1450: sometimes IE returns 1223 when it should be 204
		1223: 204
	},
	xhrSupported = jQuery.ajaxSettings.xhr();

support.cors = !!xhrSupported && ( "withCredentials" in xhrSupported );
support.ajax = xhrSupported = !!xhrSupported;

jQuery.ajaxTransport( function( options ) {
	var callback, errorCallback;

	// Cross domain only allowed if supported through XMLHttpRequest
	if ( support.cors || xhrSupported && !options.crossDomain ) {
		return {
			send: function( headers, complete ) {
				var i,
					xhr = options.xhr();

				xhr.open(
					options.type,
					options.url,
					options.async,
					options.username,
					options.password
				);

				// Apply custom fields if provided
				if ( options.xhrFields ) {
					for ( i in options.xhrFields ) {
						xhr[ i ] = options.xhrFields[ i ];
					}
				}

				// Override mime type if needed
				if ( options.mimeType && xhr.overrideMimeType ) {
					xhr.overrideMimeType( options.mimeType );
				}

				// X-Requested-With header
				// For cross-domain requests, seeing as conditions for a preflight are
				// akin to a jigsaw puzzle, we simply never set it to be sure.
				// (it can always be set on a per-request basis or even using ajaxSetup)
				// For same-domain requests, won't change header if already provided.
				if ( !options.crossDomain && !headers[ "X-Requested-With" ] ) {
					headers[ "X-Requested-With" ] = "XMLHttpRequest";
				}

				// Set headers
				for ( i in headers ) {
					xhr.setRequestHeader( i, headers[ i ] );
				}

				// Callback
				callback = function( type ) {
					return function() {
						if ( callback ) {
							callback = errorCallback = xhr.onload =
								xhr.onerror = xhr.onabort = xhr.ontimeout =
									xhr.onreadystatechange = null;

							if ( type === "abort" ) {
								xhr.abort();
							} else if ( type === "error" ) {

								// Support: IE <=9 only
								// On a manual native abort, IE9 throws
								// errors on any property access that is not readyState
								if ( typeof xhr.status !== "number" ) {
									complete( 0, "error" );
								} else {
									complete(

										// File: protocol always yields status 0; see trac-8605, trac-14207
										xhr.status,
										xhr.statusText
									);
								}
							} else {
								complete(
									xhrSuccessStatus[ xhr.status ] || xhr.status,
									xhr.statusText,

									// Support: IE <=9 only
									// IE9 has no XHR2 but throws on binary (trac-11426)
									// For XHR2 non-text, let the caller handle it (gh-2498)
									( xhr.responseType || "text" ) !== "text"  ||
									typeof xhr.responseText !== "string" ?
										{ binary: xhr.response } :
										{ text: xhr.responseText },
									xhr.getAllResponseHeaders()
								);
							}
						}
					};
				};

				// Listen to events
				xhr.onload = callback();
				errorCallback = xhr.onerror = xhr.ontimeout = callback( "error" );

				// Support: IE 9 only
				// Use onreadystatechange to replace onabort
				// to handle uncaught aborts
				if ( xhr.onabort !== undefined ) {
					xhr.onabort = errorCallback;
				} else {
					xhr.onreadystatechange = function() {

						// Check readyState before timeout as it changes
						if ( xhr.readyState === 4 ) {

							// Allow onerror to be called first,
							// but that will not handle a native abort
							// Also, save errorCallback to a variable
							// as xhr.onerror cannot be accessed
							window.setTimeout( function() {
								if ( callback ) {
									errorCallback();
								}
							} );
						}
					};
				}

				// Create the abort callback
				callback = callback( "abort" );

				try {

					// Do send the request (this may raise an exception)
					xhr.send( options.hasContent && options.data || null );
				} catch ( e ) {

					// trac-14683: Only rethrow if this hasn't been notified as an error yet
					if ( callback ) {
						throw e;
					}
				}
			},

			abort: function() {
				if ( callback ) {
					callback();
				}
			}
		};
	}
} );




// Prevent auto-execution of scripts when no explicit dataType was provided (See gh-2432)
jQuery.ajaxPrefilter( function( s ) {
	if ( s.crossDomain ) {
		s.contents.script = false;
	}
} );

// Install script dataType
jQuery.ajaxSetup( {
	accepts: {
		script: "text/javascript, application/javascript, " +
			"application/ecmascript, application/x-ecmascript"
	},
	contents: {
		script: /\b(?:java|ecma)script\b/
	},
	converters: {
		"text script": function( text ) {
			jQuery.globalEval( text );
			return text;
		}
	}
} );

// Handle cache's special case and crossDomain
jQuery.ajaxPrefilter( "script", function( s ) {
	if ( s.cache === undefined ) {
		s.cache = false;
	}
	if ( s.crossDomain ) {
		s.type = "GET";
	}
} );

// Bind script tag hack transport
jQuery.ajaxTransport( "script", function( s ) {

	// This transport only deals with cross domain or forced-by-attrs requests
	if ( s.crossDomain || s.scriptAttrs ) {
		var script, callback;
		return {
			send: function( _, complete ) {
				script = jQuery( "<script>" )
					.attr( s.scriptAttrs || {} )
					.prop( { charset: s.scriptCharset, src: s.url } )
					.on( "load error", callback = function( evt ) {
						script.remove();
						callback = null;
						if ( evt ) {
							complete( evt.type === "error" ? 404 : 200, evt.type );
						}
					} );

				// Use native DOM manipulation to avoid our domManip AJAX trickery
				document.head.appendChild( script[ 0 ] );
			},
			abort: function() {
				if ( callback ) {
					callback();
				}
			}
		};
	}
} );




var oldCallbacks = [],
	rjsonp = /(=)\?(?=&|$)|\?\?/;

// Default jsonp settings
jQuery.ajaxSetup( {
	jsonp: "callback",
	jsonpCallback: function() {
		var callback = oldCallbacks.pop() || ( jQuery.expando + "_" + ( nonce.guid++ ) );
		this[ callback ] = true;
		return callback;
	}
} );

// Detect, normalize options and install callbacks for jsonp requests
jQuery.ajaxPrefilter( "json jsonp", function( s, originalSettings, jqXHR ) {

	var callbackName, overwritten, responseContainer,
		jsonProp = s.jsonp !== false && ( rjsonp.test( s.url ) ?
			"url" :
			typeof s.data === "string" &&
				( s.contentType || "" )
					.indexOf( "application/x-www-form-urlencoded" ) === 0 &&
				rjsonp.test( s.data ) && "data"
		);

	// Handle iff the expected data type is "jsonp" or we have a parameter to set
	if ( jsonProp || s.dataTypes[ 0 ] === "jsonp" ) {

		// Get callback name, remembering preexisting value associated with it
		callbackName = s.jsonpCallback = isFunction( s.jsonpCallback ) ?
			s.jsonpCallback() :
			s.jsonpCallback;

		// Insert callback into url or form data
		if ( jsonProp ) {
			s[ jsonProp ] = s[ jsonProp ].replace( rjsonp, "$1" + callbackName );
		} else if ( s.jsonp !== false ) {
			s.url += ( rquery.test( s.url ) ? "&" : "?" ) + s.jsonp + "=" + callbackName;
		}

		// Use data converter to retrieve json after script execution
		s.converters[ "script json" ] = function() {
			if ( !responseContainer ) {
				jQuery.error( callbackName + " was not called" );
			}
			return responseContainer[ 0 ];
		};

		// Force json dataType
		s.dataTypes[ 0 ] = "json";

		// Install callback
		overwritten = window[ callbackName ];
		window[ callbackName ] = function() {
			responseContainer = arguments;
		};

		// Clean-up function (fires after converters)
		jqXHR.always( function() {

			// If previous value didn't exist - remove it
			if ( overwritten === undefined ) {
				jQuery( window ).removeProp( callbackName );

			// Otherwise restore preexisting value
			} else {
				window[ callbackName ] = overwritten;
			}

			// Save back as free
			if ( s[ callbackName ] ) {

				// Make sure that re-using the options doesn't screw things around
				s.jsonpCallback = originalSettings.jsonpCallback;

				// Save the callback name for future use
				oldCallbacks.push( callbackName );
			}

			// Call if it was a function and we have a response
			if ( responseContainer && isFunction( overwritten ) ) {
				overwritten( responseContainer[ 0 ] );
			}

			responseContainer = overwritten = undefined;
		} );

		// Delegate to script
		return "script";
	}
} );




// Support: Safari 8 only
// In Safari 8 documents created via document.implementation.createHTMLDocument
// collapse sibling forms: the second one becomes a child of the first one.
// Because of that, this security measure has to be disabled in Safari 8.
// https://bugs.webkit.org/show_bug.cgi?id=137337
support.createHTMLDocument = ( function() {
	var body = document.implementation.createHTMLDocument( "" ).body;
	body.innerHTML = "<form></form><form></form>";
	return body.childNodes.length === 2;
} )();


// Argument "data" should be string of html
// context (optional): If specified, the fragment will be created in this context,
// defaults to document
// keepScripts (optional): If true, will include scripts passed in the html string
jQuery.parseHTML = function( data, context, keepScripts ) {
	if ( typeof data !== "string" ) {
		return [];
	}
	if ( typeof context === "boolean" ) {
		keepScripts = context;
		context = false;
	}

	var base, parsed, scripts;

	if ( !context ) {

		// Stop scripts or inline event handlers from being executed immediately
		// by using document.implementation
		if ( support.createHTMLDocument ) {
			context = document.implementation.createHTMLDocument( "" );

			// Set the base href for the created document
			// so any parsed elements with URLs
			// are based on the document's URL (gh-2965)
			base = context.createElement( "base" );
			base.href = document.location.href;
			context.head.appendChild( base );
		} else {
			context = document;
		}
	}

	parsed = rsingleTag.exec( data );
	scripts = !keepScripts && [];

	// Single tag
	if ( parsed ) {
		return [ context.createElement( parsed[ 1 ] ) ];
	}

	parsed = buildFragment( [ data ], context, scripts );

	if ( scripts && scripts.length ) {
		jQuery( scripts ).remove();
	}

	return jQuery.merge( [], parsed.childNodes );
};


/**
 * Load a url into a page
 */
jQuery.fn.load = function( url, params, callback ) {
	var selector, type, response,
		self = this,
		off = url.indexOf( " " );

	if ( off > -1 ) {
		selector = stripAndCollapse( url.slice( off ) );
		url = url.slice( 0, off );
	}

	// If it's a function
	if ( isFunction( params ) ) {

		// We assume that it's the callback
		callback = params;
		params = undefined;

	// Otherwise, build a param string
	} else if ( params && typeof params === "object" ) {
		type = "POST";
	}

	// If we have elements to modify, make the request
	if ( self.length > 0 ) {
		jQuery.ajax( {
			url: url,

			// If "type" variable is undefined, then "GET" method will be used.
			// Make value of this field explicit since
			// user can override it through ajaxSetup method
			type: type || "GET",
			dataType: "html",
			data: params
		} ).done( function( responseText ) {

			// Save response for use in complete callback
			response = arguments;

			self.html( selector ?

				// If a selector was specified, locate the right elements in a dummy div
				// Exclude scripts to avoid IE 'Permission Denied' errors
				jQuery( "<div>" ).append( jQuery.parseHTML( responseText ) ).find( selector ) :

				// Otherwise use the full result
				responseText );

		// If the request succeeds, this function gets "data", "status", "jqXHR"
		// but they are ignored because response was set above.
		// If it fails, this function gets "jqXHR", "status", "error"
		} ).always( callback && function( jqXHR, status ) {
			self.each( function() {
				callback.apply( this, response || [ jqXHR.responseText, status, jqXHR ] );
			} );
		} );
	}

	return this;
};




jQuery.expr.pseudos.animated = function( elem ) {
	return jQuery.grep( jQuery.timers, function( fn ) {
		return elem === fn.elem;
	} ).length;
};




jQuery.offset = {
	setOffset: function( elem, options, i ) {
		var curPosition, curLeft, curCSSTop, curTop, curOffset, curCSSLeft, calculatePosition,
			position = jQuery.css( elem, "position" ),
			curElem = jQuery( elem ),
			props = {};

		// Set position first, in-case top/left are set even on static elem
		if ( position === "static" ) {
			elem.style.position = "relative";
		}

		curOffset = curElem.offset();
		curCSSTop = jQuery.css( elem, "top" );
		curCSSLeft = jQuery.css( elem, "left" );
		calculatePosition = ( position === "absolute" || position === "fixed" ) &&
			( curCSSTop + curCSSLeft ).indexOf( "auto" ) > -1;

		// Need to be able to calculate position if either
		// top or left is auto and position is either absolute or fixed
		if ( calculatePosition ) {
			curPosition = curElem.position();
			curTop = curPosition.top;
			curLeft = curPosition.left;

		} else {
			curTop = parseFloat( curCSSTop ) || 0;
			curLeft = parseFloat( curCSSLeft ) || 0;
		}

		if ( isFunction( options ) ) {

			// Use jQuery.extend here to allow modification of coordinates argument (gh-1848)
			options = options.call( elem, i, jQuery.extend( {}, curOffset ) );
		}

		if ( options.top != null ) {
			props.top = ( options.top - curOffset.top ) + curTop;
		}
		if ( options.left != null ) {
			props.left = ( options.left - curOffset.left ) + curLeft;
		}

		if ( "using" in options ) {
			options.using.call( elem, props );

		} else {
			curElem.css( props );
		}
	}
};

jQuery.fn.extend( {

	// offset() relates an element's border box to the document origin
	offset: function( options ) {

		// Preserve chaining for setter
		if ( arguments.length ) {
			return options === undefined ?
				this :
				this.each( function( i ) {
					jQuery.offset.setOffset( this, options, i );
				} );
		}

		var rect, win,
			elem = this[ 0 ];

		if ( !elem ) {
			return;
		}

		// Return zeros for disconnected and hidden (display: none) elements (gh-2310)
		// Support: IE <=11 only
		// Running getBoundingClientRect on a
		// disconnected node in IE throws an error
		if ( !elem.getClientRects().length ) {
			return { top: 0, left: 0 };
		}

		// Get document-relative position by adding viewport scroll to viewport-relative gBCR
		rect = elem.getBoundingClientRect();
		win = elem.ownerDocument.defaultView;
		return {
			top: rect.top + win.pageYOffset,
			left: rect.left + win.pageXOffset
		};
	},

	// position() relates an element's margin box to its offset parent's padding box
	// This corresponds to the behavior of CSS absolute positioning
	position: function() {
		if ( !this[ 0 ] ) {
			return;
		}

		var offsetParent, offset, doc,
			elem = this[ 0 ],
			parentOffset = { top: 0, left: 0 };

		// position:fixed elements are offset from the viewport, which itself always has zero offset
		if ( jQuery.css( elem, "position" ) === "fixed" ) {

			// Assume position:fixed implies availability of getBoundingClientRect
			offset = elem.getBoundingClientRect();

		} else {
			offset = this.offset();

			// Account for the *real* offset parent, which can be the document or its root element
			// when a statically positioned element is identified
			doc = elem.ownerDocument;
			offsetParent = elem.offsetParent || doc.documentElement;
			while ( offsetParent &&
				( offsetParent === doc.body || offsetParent === doc.documentElement ) &&
				jQuery.css( offsetParent, "position" ) === "static" ) {

				offsetParent = offsetParent.parentNode;
			}
			if ( offsetParent && offsetParent !== elem && offsetParent.nodeType === 1 ) {

				// Incorporate borders into its offset, since they are outside its content origin
				parentOffset = jQuery( offsetParent ).offset();
				parentOffset.top += jQuery.css( offsetParent, "borderTopWidth", true );
				parentOffset.left += jQuery.css( offsetParent, "borderLeftWidth", true );
			}
		}

		// Subtract parent offsets and element margins
		return {
			top: offset.top - parentOffset.top - jQuery.css( elem, "marginTop", true ),
			left: offset.left - parentOffset.left - jQuery.css( elem, "marginLeft", true )
		};
	},

	// This method will return documentElement in the following cases:
	// 1) For the element inside the iframe without offsetParent, this method will return
	//    documentElement of the parent window
	// 2) For the hidden or detached element
	// 3) For body or html element, i.e. in case of the html node - it will return itself
	//
	// but those exceptions were never presented as a real life use-cases
	// and might be considered as more preferable results.
	//
	// This logic, however, is not guaranteed and can change at any point in the future
	offsetParent: function() {
		return this.map( function() {
			var offsetParent = this.offsetParent;

			while ( offsetParent && jQuery.css( offsetParent, "position" ) === "static" ) {
				offsetParent = offsetParent.offsetParent;
			}

			return offsetParent || documentElement;
		} );
	}
} );

// Create scrollLeft and scrollTop methods
jQuery.each( { scrollLeft: "pageXOffset", scrollTop: "pageYOffset" }, function( method, prop ) {
	var top = "pageYOffset" === prop;

	jQuery.fn[ method ] = function( val ) {
		return access( this, function( elem, method, val ) {

			// Coalesce documents and windows
			var win;
			if ( isWindow( elem ) ) {
				win = elem;
			} else if ( elem.nodeType === 9 ) {
				win = elem.defaultView;
			}

			if ( val === undefined ) {
				return win ? win[ prop ] : elem[ method ];
			}

			if ( win ) {
				win.scrollTo(
					!top ? val : win.pageXOffset,
					top ? val : win.pageYOffset
				);

			} else {
				elem[ method ] = val;
			}
		}, method, val, arguments.length );
	};
} );

// Support: Safari <=7 - 9.1, Chrome <=37 - 49
// Add the top/left cssHooks using jQuery.fn.position
// Webkit bug: https://bugs.webkit.org/show_bug.cgi?id=29084
// Blink bug: https://bugs.chromium.org/p/chromium/issues/detail?id=589347
// getComputedStyle returns percent when specified for top/left/bottom/right;
// rather than make the css module depend on the offset module, just check for it here
jQuery.each( [ "top", "left" ], function( _i, prop ) {
	jQuery.cssHooks[ prop ] = addGetHookIf( support.pixelPosition,
		function( elem, computed ) {
			if ( computed ) {
				computed = curCSS( elem, prop );

				// If curCSS returns percentage, fallback to offset
				return rnumnonpx.test( computed ) ?
					jQuery( elem ).position()[ prop ] + "px" :
					computed;
			}
		}
	);
} );


// Create innerHeight, innerWidth, height, width, outerHeight and outerWidth methods
jQuery.each( { Height: "height", Width: "width" }, function( name, type ) {
	jQuery.each( {
		padding: "inner" + name,
		content: type,
		"": "outer" + name
	}, function( defaultExtra, funcName ) {

		// Margin is only for outerHeight, outerWidth
		jQuery.fn[ funcName ] = function( margin, value ) {
			var chainable = arguments.length && ( defaultExtra || typeof margin !== "boolean" ),
				extra = defaultExtra || ( margin === true || value === true ? "margin" : "border" );

			return access( this, function( elem, type, value ) {
				var doc;

				if ( isWindow( elem ) ) {

					// $( window ).outerWidth/Height return w/h including scrollbars (gh-1729)
					return funcName.indexOf( "outer" ) === 0 ?
						elem[ "inner" + name ] :
						elem.document.documentElement[ "client" + name ];
				}

				// Get document width or height
				if ( elem.nodeType === 9 ) {
					doc = elem.documentElement;

					// Either scroll[Width/Height] or offset[Width/Height] or client[Width/Height],
					// whichever is greatest
					return Math.max(
						elem.body[ "scroll" + name ], doc[ "scroll" + name ],
						elem.body[ "offset" + name ], doc[ "offset" + name ],
						doc[ "client" + name ]
					);
				}

				return value === undefined ?

					// Get width or height on the element, requesting but not forcing parseFloat
					jQuery.css( elem, type, extra ) :

					// Set width or height on the element
					jQuery.style( elem, type, value, extra );
			}, type, chainable ? margin : undefined, chainable );
		};
	} );
} );


jQuery.each( [
	"ajaxStart",
	"ajaxStop",
	"ajaxComplete",
	"ajaxError",
	"ajaxSuccess",
	"ajaxSend"
], function( _i, type ) {
	jQuery.fn[ type ] = function( fn ) {
		return this.on( type, fn );
	};
} );




jQuery.fn.extend( {

	bind: function( types, data, fn ) {
		return this.on( types, null, data, fn );
	},
	unbind: function( types, fn ) {
		return this.off( types, null, fn );
	},

	delegate: function( selector, types, data, fn ) {
		return this.on( types, selector, data, fn );
	},
	undelegate: function( selector, types, fn ) {

		// ( namespace ) or ( selector, types [, fn] )
		return arguments.length === 1 ?
			this.off( selector, "**" ) :
			this.off( types, selector || "**", fn );
	},

	hover: function( fnOver, fnOut ) {
		return this
			.on( "mouseenter", fnOver )
			.on( "mouseleave", fnOut || fnOver );
	}
} );

jQuery.each(
	( "blur focus focusin focusout resize scroll click dblclick " +
	"mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave " +
	"change select submit keydown keypress keyup contextmenu" ).split( " " ),
	function( _i, name ) {

		// Handle event binding
		jQuery.fn[ name ] = function( data, fn ) {
			return arguments.length > 0 ?
				this.on( name, null, data, fn ) :
				this.trigger( name );
		};
	}
);




// Support: Android <=4.0 only
// Make sure we trim BOM and NBSP
// Require that the "whitespace run" starts from a non-whitespace
// to avoid O(N^2) behavior when the engine would try matching "\s+$" at each space position.
var rtrim = /^[\s\uFEFF\xA0]+|([^\s\uFEFF\xA0])[\s\uFEFF\xA0]+$/g;

// Bind a function to a context, optionally partially applying any
// arguments.
// jQuery.proxy is deprecated to promote standards (specifically Function#bind)
// However, it is not slated for removal any time soon
jQuery.proxy = function( fn, context ) {
	var tmp, args, proxy;

	if ( typeof context === "string" ) {
		tmp = fn[ context ];
		context = fn;
		fn = tmp;
	}

	// Quick check to determine if target is callable, in the spec
	// this throws a TypeError, but we will just return undefined.
	if ( !isFunction( fn ) ) {
		return undefined;
	}

	// Simulated bind
	args = slice.call( arguments, 2 );
	proxy = function() {
		return fn.apply( context || this, args.concat( slice.call( arguments ) ) );
	};

	// Set the guid of unique handler to the same of original handler, so it can be removed
	proxy.guid = fn.guid = fn.guid || jQuery.guid++;

	return proxy;
};

jQuery.holdReady = function( hold ) {
	if ( hold ) {
		jQuery.readyWait++;
	} else {
		jQuery.ready( true );
	}
};
jQuery.isArray = Array.isArray;
jQuery.parseJSON = JSON.parse;
jQuery.nodeName = nodeName;
jQuery.isFunction = isFunction;
jQuery.isWindow = isWindow;
jQuery.camelCase = camelCase;
jQuery.type = toType;

jQuery.now = Date.now;

jQuery.isNumeric = function( obj ) {

	// As of jQuery 3.0, isNumeric is limited to
	// strings and numbers (primitives or objects)
	// that can be coerced to finite numbers (gh-2662)
	var type = jQuery.type( obj );
	return ( type === "number" || type === "string" ) &&

		// parseFloat NaNs numeric-cast false positives ("")
		// ...but misinterprets leading-number strings, particularly hex literals ("0x...")
		// subtraction forces infinities to NaN
		!isNaN( obj - parseFloat( obj ) );
};

jQuery.trim = function( text ) {
	return text == null ?
		"" :
		( text + "" ).replace( rtrim, "$1" );
};



// Register as a named AMD module, since jQuery can be concatenated with other
// files that may use define, but not via a proper concatenation script that
// understands anonymous AMD modules. A named AMD is safest and most robust
// way to register. Lowercase jquery is used because AMD module names are
// derived from file names, and jQuery is normally delivered in a lowercase
// file name. Do this after creating the global so that if an AMD module wants
// to call noConflict to hide this version of jQuery, it will work.

// Note that for maximum portability, libraries that are not jQuery should
// declare themselves as anonymous modules, and avoid setting a global if an
// AMD loader is present. jQuery is a special case. For more information, see
// https://github.com/jrburke/requirejs/wiki/Updating-existing-libraries#wiki-anon

if ( true ) {
	!(__WEBPACK_AMD_DEFINE_ARRAY__ = [], __WEBPACK_AMD_DEFINE_RESULT__ = (function() {
		return jQuery;
	}).apply(exports, __WEBPACK_AMD_DEFINE_ARRAY__),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));
}




var

	// Map over jQuery in case of overwrite
	_jQuery = window.jQuery,

	// Map over the $ in case of overwrite
	_$ = window.$;

jQuery.noConflict = function( deep ) {
	if ( window.$ === jQuery ) {
		window.$ = _$;
	}

	if ( deep && window.jQuery === jQuery ) {
		window.jQuery = _jQuery;
	}

	return jQuery;
};

// Expose jQuery and $ identifiers, even in AMD
// (trac-7102#comment:10, https://github.com/jquery/jquery/pull/557)
// and CommonJS for browser emulators (trac-13566)
if ( typeof noGlobal === "undefined" ) {
	window.jQuery = window.$ = jQuery;
}




return jQuery;
} );


/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Check if module exists (development only)
/******/ 		if (__webpack_modules__[moduleId] === undefined) {
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be in strict mode.
(() => {
"use strict";
var exports = __webpack_exports__;
/*!*****************!*\
  !*** ./Main.ts ***!
  \*****************/

/****************************************************************************
  ______ _ _         _
 |  ____| | |       (_)
 | |__  | | | ____ _ _ ___  __ _ _ __
 |  __| | | |/ / _` | / __|/ _` | '__|
 | |____| |   < (_| | \__ \ (_| | |
 |______|_|_|\_\__,_|_|___/\__,_|_|

 ****************************************************************************/
Object.defineProperty(exports, "__esModule", ({ value: true }));
const Loading_Scene_1 = __webpack_require__(/*! ./Src/Scene/Loading.Scene */ "./Src/Scene/Loading.Scene.ts");
/**
 * A brief explanation for "project.json":
 * Here is the content of project.json file, this is the global configuration for your game, you can modify it to customize some behavior.
 * The detail of each field is under it.
  {
    "project_type": "javascript",
    // "project_type" indicate the program language of your project, you can ignore this field

    "debugMode"     : 1,
    // "debugMode" possible values :
    //      0 - No message will be printed.
    //      1 - cc.error, cc.assert, cc.warn, cc.log will print in console.
    //      2 - cc.error, cc.assert, cc.warn will print in console.
    //      3 - cc.error, cc.assert will print in console.
    //      4 - cc.error, cc.assert, cc.warn, cc.log will print on canvas, available only on web.
    //      5 - cc.error, cc.assert, cc.warn will print on canvas, available only on web.
    //      6 - cc.error, cc.assert will print on canvas, available only on web.

    "showFPS"       : true,
    // Left bottom corner fps information will show when "showFPS" equals true, otherwise it will be hide.

    "frameRate"     : 60,
    // "frameRate" set the wanted frame rate for your game, but the real fps depends on your game implementation and the running environment.

    "noCache"       : false,
    // "noCache" set whether your resources will be loaded with a timestamp suffix in the url.
    // In this way, your resources will be force updated even if the browser holds a cache of it.
    // It's very useful for mobile browser debugging.

    "id"            : "gameCanvas",
    // "gameCanvas" sets the id of your canvas element on the web page, it's useful only on web.

    "renderMode"    : 0,
    // "renderMode" sets the renderer type, only useful on web :
    //      0 - Automatically chosen by engine
    //      1 - Forced to use canvas renderer
    //      2 - Forced to use WebGL renderer, but this will be ignored on mobile browsers

    "engineDir"     : "frameworks/cocos2d-html5/",
    // In debug mode, if you use the whole engine to develop your game, you should specify its relative path with "engineDir",
    // but if you are using a single engine file, you can ignore it.

    "modules"       : ["cocos2d"],
    // "modules" defines which modules you will need in your game, it's useful only on web,
    // using this can greatly reduce your game's resource size, and the cocos console tool can package your game with only the modules you set.
    // For details about modules definitions, you can refer to "../../frameworks/cocos2d-html5/modulesConfig.json".

    "jsList"        : [
    ]
    // "jsList" sets the list of js files in your game.
  }
 *
*/
/****************************************************************************
  Copyright (c) 2017-2018 Xiamen Yaji Software Co., Ltd.

  http://www.cocos2d-x.org

  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the Software is
  furnished to do so, subject to the following conditions:

  The above copyright notice and this permission notice shall be included in
  all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
  THE SOFTWARE.
 ****************************************************************************/
/**
 * A brief explanation for "project.json":
 * Here is the content of project.json file, this is the global configuration for your game, you can modify it to customize some behavior.
 * The detail of each field is under it.
 *
 */
__webpack_require__(/*! ../Static/css/reset-min.css */ "../Static/css/reset-min.css");
__webpack_require__(/*! ../Static/css/fonts-min.css */ "../Static/css/fonts-min.css");
__webpack_require__(/*! ../Static/css/alert-box.css */ "../Static/css/alert-box.css");
__webpack_require__(/*! ../Static/css/base.css */ "../Static/css/base.css");
__webpack_require__(/*! ../Static/css/city.css */ "../Static/css/city.css");
__webpack_require__(/*! ../Static/css/world.css */ "../Static/css/world.css");
__webpack_require__(/*! ../Static/css/lastStyle.css */ "../Static/css/lastStyle.css");
__webpack_require__(/*! ../Static/css/lastOfLast.css */ "../Static/css/lastOfLast.css");
// import "../../Static/css/finishing.css";
// import "../../Static/css/tooltips.css";
// import "../../Static/css/animation.css";
// import "../../Static/css/hero.css";
// import "../../Static/css/godPoints.css";
__webpack_require__(/*! ../Static/css/ui.css */ "../Static/css/ui.css");
// import "../../Static/css/team.css";
const scaleScreen = () => {
    const lSizeInPixels = cc.view.getFrameSize();
    const w = lSizeInPixels.width;
    const h = lSizeInPixels.height;
    const CONFIG_SCREEN_WIDTH = 1140 * 1.25;
    const lScaleX = w / CONFIG_SCREEN_WIDTH;
    const lScale = lScaleX;
    let CONFIG_SCREEN_HEIGHT = h / lScale;
    const lPolicyParam = typeof window !== "undefined"
        ? new URLSearchParams(window.location.search).get("policy")
        : null;
    const lUseShowAll = lPolicyParam?.toLowerCase() === "show_all";
    const lResolutionPolicy = lUseShowAll
        ? cc.ResolutionPolicy.SHOW_ALL
        : cc.ResolutionPolicy.NO_BORDER;
    cc.view.setDesignResolutionSize(CONFIG_SCREEN_WIDTH, CONFIG_SCREEN_HEIGHT, lResolutionPolicy);
    cc.log(`[ResolutionPolicy] ${lUseShowAll ? "SHOW_ALL" : "NO_BORDER"} ` +
        `(use ?policy=show_all to toggle)`);
};
cc.game.onStart = async function () {
    cc.log("Game is starting...");
    var sys = cc.sys;
    if (!sys.isNative && document.getElementById("cocosLoading"))
        //If referenced loading.js, please remove it
        document.body.removeChild(document.getElementById("cocosLoading"));
    cc.director.setClearColor(cc.color(255, 255, 255, 255));
    // Pass true to enable retina display, on Android disabled by default to improve performance
    cc.view.enableRetina(true);
    // cc.view.enableRetina(sys.os === sys.OS_IOS ? true : false);
    // Disable auto full screen on baidu and wechat, you might also want to eliminate sys.BROWSER_TYPE_MOBILE_QQ
    if (sys.isMobile &&
        sys.browserType !== sys.BROWSER_TYPE_BAIDU &&
        sys.browserType !== sys.BROWSER_TYPE_WECHAT) {
        cc.view.enableAutoFullScreen(true);
    }
    // Adjust viewport meta
    cc.view.adjustViewPort(true);
    if (cc.sys.isNative) {
    }
    scaleScreen();
    // Uncomment the following line to set a fixed orientation for your game
    cc.view.setOrientation(cc.ORIENTATION_LANDSCAPE_LEFT);
    if (!cc.sys.isNative) {
        cc.view.resizeWithBrowserSize(true);
    }
    cc.UISize = cc.size(640, 960);
    // cc.error = (...any: any[]) => {
    //   const err = new Error();
    //   cc.log(
    //     "[Error] : ",
    //     ...any,
    //     `At \n ${enhanceSpiderMonkeyStackTrace(err.stack!)}`,
    //   );
    // };
    cc.log("GameManager initialized successfully");
    // const lCityScene = await CityScene.Create();
    // await lCityScene.EnterScene();
    const lScene = Loading_Scene_1.CLoadingScene.Create();
    cc.director.runScene(lScene);
    lScene.EnterScene();
    // lScene.NavigateTo(423, 44, false);
};
// if (!cc.sys.isNative) {
//   window.addEventListener(
//     "resize",
//     throttle(() => {
//       // Update canvas dimensions
//       // let canvas = document.getElementById("GameWindow")! as HTMLCanvasElement;
//       // canvas.width = window.innerWidth;
//       // canvas.height = window.innerHeight;
//       // Update Cocos view
//       // cc.view.setDesignResolutionSize(
//       //   window.innerWidth,
//       //   window.innerHeight,
//       //   cc.ResolutionPolicy.NO_BORDER
//       // );
//     }, 200),
//   );
// } else {
//   cc.game.run();
//   XLog.log("Running in native mode");
// }

})();

module.exports = __webpack_exports__;
/******/ })()
;