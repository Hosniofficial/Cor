const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

function resolved(value) {
    return {
        then(callback) {
            return resolved(callback(value));
        },
        fail() {
            return this;
        },
        always(callback) {
            callback();
            return this;
        }
    };
}

test('expired study refreshes player education and the open academy list', () => {
    const start = clientSource.indexOf('Caesar.TimedTask._expiredTaskSyncAt = {};');
    const endMarker = '\nCaesar.TimedTask.refreshExpiredTasks';
    const end = clientSource.indexOf(endMarker, start);
    assert.ok(start >= 0 && end > start, 'expired task refresh method should exist');

    let educationRefreshes = 0;
    let updatedStudyLists = [];
    let educationLevelAtRequest = 0;
    const nowSeconds = Math.floor(Date.now() / 1000);
    const context = {
        Caesar: {
            DPlayer: { PlayerEdu: { farming: 4 } },
            TimedTask: {
                _expiredTaskSyncAt: {},
                TaskList: {
                    Study: {
                        41: { id_city: 9, time_end: nowSeconds - 1, study: 'farming', lvl_to: 5 }
                    }
                },
                getCityBuildingTasks: () => resolved(),
                getCityStudyTasks: () => resolved(),
                getCityArmyTasks: () => resolved(),
                getCityJopTasks: () => resolved()
            },
            City: {
                getCityBase: () => resolved()
            }
        },
        Player_profile: {
            getPlayerBaseData: () => resolved(),
            getPlayerEdu() {
                educationRefreshes++;
                educationLevelAtRequest = CaesarContext.DPlayer.PlayerEdu.farming;
                CaesarContext.DPlayer.PlayerEdu.farming = 4;
                return resolved();
            }
        },
        edu: {
            getUniTech: () => '<ul>university studies</ul>',
            getAcadTech: () => `<ul>academy level ${CaesarContext.DPlayer.PlayerEdu.farming}</ul>`
        },
        $: function(selector) {
            return {
                attr: () => selector === '#dialg_box .for_building_box'
                    ? 'study_acad'
                    : undefined,
                replaceWith(content) {
                    updatedStudyLists.push(content);
                    return this;
                }
            };
        }
    };
    const CaesarContext = context.Caesar;
    context.$.when = () => resolved();

    vm.runInNewContext(clientSource.slice(start, end), context);
    context.Caesar.TimedTask.refreshExpiredTaskState(9);

    assert.equal(educationRefreshes, 1);
    assert.equal(educationLevelAtRequest, 5, 'the completed level should be applied before the education request');
    assert.deepEqual(updatedStudyLists, [
        '<ul>academy level 5</ul>',
        '<ul>academy level 5</ul>'
    ]);
});

test('study task removal refreshes education and player prestige like building task removal', () => {
    const assignment = 'Caesar.TimedTask.replaceCityTasks = function(taskType, idCity, tasks) {';
    const start = clientSource.indexOf(assignment);
    const end = clientSource.indexOf('\n\nCaesar.TimedTask.refreshCityBuildingView', start);
    assert.ok(start >= 0 && end > start, 'city task replacement should exist');

    const nowSeconds = Math.floor(Date.now() / 1000);
    let refreshedEducation = null;
    let playerRefreshes = 0;
    const context = {
        Caesar: {
            TimedTask: {
                TaskList: {
                    Study: {
                        41: {
                            id: 41,
                            id_city: 9,
                            study: 'farming',
                            lvl_to: 5,
                            time_end: nowSeconds - 1
                        }
                    }
                },
                _expiredTaskSyncInFlight: {},
                refreshListView() {},
                refreshCompletedStudyView(idCity, completedStudies) {
                    refreshedEducation = { idCity, completedStudies };
                    return resolved();
                }
            }
        },
        Player_profile: {
            getPlayerBaseData() {
                playerRefreshes++;
                return resolved();
            }
        },
        console: { error() {} },
        Date
    };

    vm.runInNewContext(clientSource.slice(start, end), context);
    context.Caesar.TimedTask.replaceCityTasks('Study', 9, []);

    assert.equal(refreshedEducation.idCity, 9);
    assert.equal(refreshedEducation.completedStudies.farming, 5);
    assert.equal(playerRefreshes, 1);
});

test('completed study speed-up refreshes education and prestige immediately', () => {
    const start = clientSource.indexOf('else if (user_for === "study_acce") {');
    const end = clientSource.indexOf('\n    /*   house   change', start);
    assert.ok(start >= 0 && end > start, 'study speed-up handler should exist');
    const branch = clientSource.slice(start, end);
    const bodyStart = branch.indexOf('{') + 1;
    const bodyEnd = branch.lastIndexOf('}');

    const studyTask = {
        id: 73,
        id_city: 9,
        study: 'farming',
        lvl_to: 5
    };
    let refreshedEducation = null;
    let playerRefreshes = 0;
    let openedBuilding = false;
    const context = {
        API_URL: 'http://localhost:8092',
        idCity: 9,
        other: 73,
        matrial: 'archim_a',
        Caesar: {
            Config: { idServer: 1, AuthToken: 'test-token' },
            LBase: { isJson: () => true },
            TimedTask: {
                TaskList: { Study: { 73: studyTask } },
                refreshListView() {},
                refreshCompletedStudyView(idCity, completedStudies) {
                    refreshedEducation = { idCity, completedStudies };
                    return resolved();
                }
            }
        },
        $: Object.assign(function() {
            return {
                removeAttr() { return this; },
                remove() { return this; },
                attr() { return 'acad-place'; }
            };
        }, {
            ajax(options) {
                options.success(JSON.stringify({ state: 'ok', list: [] }));
            }
        }),
        Matrial: { takeFrom() {} },
        Player_profile: {
            getPlayerBaseData() {
                playerRefreshes++;
                return resolved();
            }
        },
        buildingClick() {
            openedBuilding = true;
        },
        console: { error() {} }
    };

    vm.runInNewContext(
        `(function() {${branch.slice(bodyStart, bodyEnd)}})`,
        context
    )();

    assert.equal(refreshedEducation.idCity, 9);
    assert.equal(refreshedEducation.completedStudies.farming, 5);
    assert.equal(playerRefreshes, 1);
    assert.equal(openedBuilding, true);
});

test('study rejection shows the server reason, re-enables the button, and refreshes prerequisites', () => {
    const start = clientSource.indexOf('$(document).on("click", ".edu_study", function() {');
    const end = clientSource.indexOf('\n\nfunction upgradeStudy', start);
    assert.ok(start >= 0 && end > start, 'study submission handler should exist');

    let clickHandler = null;
    let errorMessage = '';
    let studyTitle = '';
    let cityRefreshes = 0;
    let educationRefreshes = 0;
    let taskRefreshes = 0;
    let buildingRefreshes = 0;
    let buttonDisabled = false;
    const button = {
        attr(name, value) {
            if (name === 'data-tech')
                return 'logistic';
            if (name === 'disabled')
                buttonDisabled = true;
            return this;
        },
        removeAttr(name) {
            if (name === 'disabled')
                buttonDisabled = false;
            return this;
        },
        hasClass() { return false; }
    };
    const context = {
        document: {},
        API_URL: 'http://localhost:8092',
        waitCursor() {},
        unwaitCursor() {},
        Caesar: {
            BaseData: { Edu: { logistic: { ar_title: 'الدعم اللوجستي' } } },
            DPlayer: { PlayerEdu: { logistic: 3 } },
            CurrentCity: { idCity: 9 },
            Config: { idServer: 1, AuthToken: 'test-token' },
            LBase: { isJson: () => true },
            City: {
                getCityBase() {
                    cityRefreshes++;
                    return resolved();
                }
            },
            TimedTask: {
                refreshListView() {},
                getCityStudyTasks() {
                    taskRefreshes++;
                    return resolved();
                }
            }
        },
        $: null,
        edu: { isUpgradable: () => true },
        Player_profile: {
            getPlayerEdu() {
                educationRefreshes++;
                return resolved();
            }
        },
        alert_box: {
            studyFailure(title, message) {
                studyTitle = title;
                errorMessage = message;
            }
        },
        buildingClick() {
            buildingRefreshes++;
        },
        city_profile: { refresh_resource_view() {} },
        console: { error() {} },
        JSON
    };
    context.$ = Object.assign(function(selector) {
        if (selector === context.document) {
            return {
                on(eventName, targetSelector, handler) {
                    if (eventName === 'click' && targetSelector === '.edu_study')
                        clickHandler = handler;
                }
            };
        }
        if (selector === button)
            return button;
        if (selector === '.for_building_box')
            return { attr: () => 'study_acad' };
        if (selector === '#dialg_box .box_header')
            return { attr: () => 'academy-place' };
        return {};
    }, {
        when: () => resolved(),
        ajax(options) {
            options.beforeSend();
            options.success(JSON.stringify({
                state: 'error_cond',
                msg: 'تتطلب الدراسة مستوى الأكاديمية 12'
            }));
        }
    });

    vm.runInNewContext(clientSource.slice(start, end), context);
    assert.ok(clickHandler, 'study click callback should register');
    clickHandler.call(button);

    assert.equal(errorMessage, 'تتطلب الدراسة مستوى الأكاديمية 12');
    assert.equal(studyTitle, 'الدعم اللوجستي');
    assert.equal(buttonDisabled, false);
    assert.equal(cityRefreshes, 1);
    assert.equal(educationRefreshes, 1);
    assert.equal(taskRefreshes, 1);
    assert.equal(buildingRefreshes, 1);
});

test('university and academy study speed controls use the confirmation button style', () => {
    const studyTechMarkup = clientSource.slice(
        clientSource.indexOf('getUniTech: function()'),
        clientSource.indexOf('getReqTable: function', clientSource.indexOf('getUniTech: function()'))
    );
    assert.match(studyTechMarkup, /class="current_lvl_study"/);
    assert.doesNotMatch(studyTechMarkup, /class="city_lvl_study"/);
    assert.doesNotMatch(clientSource, /building_lvl_lable_3.*lable_3\.png/);

    assert.ok(clientSource.includes(
        'class="speed-up-btn full-btn full-btn-3x edu_acce"'
    ), 'study speed buttons should use the confirmed material-button classes');
    assert.doesNotMatch(
        clientSource.slice(
            clientSource.indexOf('getUniTech: function()'),
            clientSource.indexOf('getReqTable: function', clientSource.indexOf('getUniTech: function()'))
        ),
        /<h6>\s*تسريع/
    );

    const finishingCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'finishing.css'),
        'utf8'
    );
    assert.match(finishingCss, /#fade-msg\s*\{/);
    assert.match(finishingCss, /#fade-msg\s*\{[^}]*top:\s*50%/s);
    assert.match(finishingCss, /animation:\s*game-notice-float\s+3000ms/);
    assert.match(finishingCss, /@keyframes game-notice-float\s*\{[\s\S]*100%\s*\{[^}]*opacity:\s*0[^}]*transform:\s*translate\(-50%,\s*-250px\)/);
    assert.doesNotMatch(finishingCss.match(/#fade-msg\s*\{([^}]*)\}/s)[1], /border\s*:/);
    assert.match(clientSource, /showCenteredNotice:\s*function\(msg,\s*type,\s*options\)/);
    assert.match(clientSource, /succesMessage:\s*function\(msg\)\s*\{[\s\S]*showCenteredNotice\(msg,\s*"success"\)/);
    assert.match(clientSource, /failMessage:\s*function\(msg\)\s*\{[\s\S]*showCenteredNotice\(msg,\s*"fail"\)/);
    assert.match(clientSource, /confirmMessage:\s*function\(title\)\s*\{[\s\S]*showCenteredNotice\(title,\s*"warning"\)/);
    assert.match(clientSource, /warningMessage:\s*function\(msg\)\s*\{[\s\S]*showCenteredNotice\(msg,\s*"warning"\)/);
    assert.match(clientSource, /game-centered-notice-action/);
    assert.match(clientSource, /window\.gameNoticeTimer\s*=\s*window\.setTimeout/);
    assert.match(clientSource, /studyFailure: function\(studyTitle, reason\)/);
    assert.doesNotMatch(clientSource, /\bwindow\.alert\s*\(/);
    assert.doesNotMatch(clientSource, /\bconfirm\s*\(/);
    assert.match(clientSource, /#trade-guild-position[\s\S]*?alert_box\.confirmDialog\("تأكيد نقل منصب المدير إلى عضو آخر بالحلف"/);
    assert.doesNotMatch(finishingCss, /background\/lvl_lable\/lable_3\.png/);
    assert.doesNotMatch(finishingCss, /\.city_lvl_study\s*\{/);
    const studySpeedRule = finishingCss.match(
        /\.study_unite \.btn-wrapper \.speed-up-btn\s*\{([^}]*)\}/
    );
    assert.ok(studySpeedRule, 'study speed button style should exist');
    assert.match(studySpeedRule[1], /font-size:\s*13px/);
    assert.match(studySpeedRule[1], /height:\s*22px/);
    assert.match(studySpeedRule[1], /width:\s*63px/);
    assert.match(finishingCss, /\.study_unite \.btn-wrapper \.speed-up-btn:hover\s*\{\s*background-image: url\(\.\.\/images\/btns\/building\/upgrade-h\.png\);/);
    assert.match(finishingCss, /\.study_unite \.btn-wrapper \.speed-up-btn:active\s*\{\s*background-image: url\(\.\.\/images\/btns\/building\/upgrade-a\.png\);/);
});

test('study completion handler preserves the target level until education reload finishes', () => {
    const start = clientSource.indexOf('Caesar.WsLib.TimedTask.Study = function(data) {');
    const end = clientSource.indexOf('\n\n\n\nfunction changeTimeFormat', start);
    assert.ok(start >= 0 && end > start, 'study WebSocket completion handler should exist');

    let completedLevel = 0;
    let refreshedView = false;
    const context = {
        Caesar: {
            DPlayer: { PlayerEdu: { farming: 4 }, Player: { prestige: 100 } },
            WsLib: { TimedTask: {} },
            TimedTask: {
                TaskList: {
                    Study: {
                        73: {
                            id: 73,
                            id_city: 9,
                            study: 'farming',
                            lvl_to: 5
                        }
                    }
                },
                refreshListView() {}
            },
            CurrentCity: { idCity: 9, City: { console: 0 } }
        },
        data: null,
        city_profile: {
            refresh_city_resources() {},
            refresh_view() {}
        },
        Player_profile: {
            refresh_view() {}
        },
        completedRefresh() {},
        getHeroById: () => null,
        army: { middle_content: () => '' }
    };
    context.Caesar.TimedTask.refreshCompletedStudyView = function(idCity, completedStudies) {
        completedLevel = completedStudies.farming || 0;
        refreshedView = true;
    };
    vm.runInNewContext(clientSource.slice(start, end), context);
    context.Caesar.WsLib.TimedTask.Study({
        prestige: 25,
        Task: { id: 73 }
    });

    assert.equal(completedLevel, 5);
    assert.equal(refreshedView, true);
    assert.equal(context.Caesar.DPlayer.Player.prestige, 125);
});
