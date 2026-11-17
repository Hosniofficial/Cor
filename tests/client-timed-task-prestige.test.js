const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

function getBuildingTaskLoader(context) {
    const assignment = 'Caesar.TimedTask.getCityBuildingTasks = ';
    const start = clientSource.indexOf(assignment);
    const end = clientSource.indexOf('\n\nCaesar.TimedTask.replaceCityTasks', start);
    assert.ok(start >= 0 && end > start, 'building task loader should exist');
    return vm.runInNewContext(
        `(${clientSource.slice(start + assignment.length, end).replace(/;\s*$/, '')})`,
        context
    );
}

function getExpiredBuildingTaskRefresher(context) {
    const assignment = 'Caesar.TimedTask.refreshExpiredBuildingTask = ';
    const start = clientSource.indexOf(assignment);
    const end = clientSource.indexOf('\n\nCaesar.TimedTask._expiredTaskSyncAt', start);
    assert.ok(start >= 0 && end > start, 'expired building task refresher should exist');
    return vm.runInNewContext(
        `(${clientSource.slice(start + assignment.length, end).replace(/;\s*$/, '')})`,
        context
    );
}

function createTaskLoaderContext(response, task) {
    const calls = { playerRefreshes: 0 };
    const context = {
        API_URL: 'http://localhost:8092',
        Caesar: {
            Config: { idServer: 1, AuthToken: 'test-token' },
            LBase: {
                isJson: () => true,
                Error: message => { throw new Error(String(message)); }
            },
            TimedTask: {
                TaskList: { Building: task ? { [task.id]: task } : {} },
                refreshListView() {}
            }
        },
        Player_profile: {
            getPlayerBaseData() {
                calls.playerRefreshes++;
                return { fail() { return this; } };
            }
        },
        $: {
            ajax(options) {
                options.success(JSON.stringify(response));
                return {};
            }
        },
        JSON
    };
    return { context, calls };
}

test('refreshes player prestige when an expired building task disappears from the server list', () => {
    const expiredTask = {
        id: 41,
        id_city: 22,
        time_end: Math.floor(Date.now() / 1000) - 1
    };
    const { context, calls } = createTaskLoaderContext([], expiredTask);

    getBuildingTaskLoader(context)(22);

    assert.equal(calls.playerRefreshes, 1);
    assert.deepEqual(Object.keys(context.Caesar.TimedTask.TaskList.Building), []);
});

test('does not refresh player prestige while the building task remains active', () => {
    const activeTask = {
        id: 41,
        id_city: 22,
        time_end: Math.floor(Date.now() / 1000) + 60
    };
    const { context, calls } = createTaskLoaderContext([activeTask], activeTask);

    getBuildingTaskLoader(context)(22);

    assert.equal(calls.playerRefreshes, 0);
    assert.equal(context.Caesar.TimedTask.TaskList.Building[41].id, 41);
});

test('refreshes player prestige when the server completes a task before the local timer ends', () => {
    const locallyActiveTask = {
        id: 41,
        id_city: 22,
        time_end: Math.floor(Date.now() / 1000) + 3600
    };
    const { context, calls } = createTaskLoaderContext([], locallyActiveTask);

    getBuildingTaskLoader(context)(22);

    assert.equal(calls.playerRefreshes, 1);
    assert.deepEqual(Object.keys(context.Caesar.TimedTask.TaskList.Building), []);
});

test('refreshes player prestige even when the completed building view fails to refresh', () => {
    const task = {
        id: 41,
        id_city: 22,
        place: 'farm',
        time_end: Math.floor(Date.now() / 1000) - 1
    };
    const calls = { playerRefreshes: 0 };
    const context = {
        API_URL: 'http://localhost:8092',
        Caesar: {
            Config: { idServer: 1, AuthToken: 'test-token' },
            LBase: {
                isJson: () => true,
                Error: message => { throw new Error(String(message)); }
            },
            TimedTask: {
                TaskList: { Building: { [task.id]: task } },
                refreshListView() {},
                refreshCityBuildingView() {
                    return {
                        fail(callback) {
                            callback({ status: 500, statusText: 'refresh failed' });
                            return this;
                        }
                    };
                }
            }
        },
        Player_profile: {
            getPlayerBaseData() {
                calls.playerRefreshes++;
                return { fail() { return this; } };
            }
        },
        $: {
            ajax(options) {
                options.success('[]');
                return {};
            }
        },
        console: { error() {} },
        Date,
        JSON
    };

    getExpiredBuildingTaskRefresher(context)(task);

    assert.equal(calls.playerRefreshes, 1);
});
