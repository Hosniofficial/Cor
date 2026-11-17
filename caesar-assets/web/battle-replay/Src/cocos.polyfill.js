var globalThis = (function () {
  return (
    this ||
    (typeof window !== "undefined"
      ? window
      : typeof global !== "undefined"
      ? global
      : {})
  );
})();

var module = {
  exports: {},
};

if (!String.prototype.padStart) {
  String.prototype.padStart = function (targetLength, padString) {
    targetLength = Math.floor(targetLength) || 0;
    if (targetLength < this.length) return String(this);

    padString = padString !== undefined ? String(padString) : " ";
    if (padString.length === 0) padString = " ";

    const pad = padString.repeat(
      Math.ceil((targetLength - this.length) / padString.length)
    );
    return pad.substring(0, targetLength - this.length) + this;
  };
}

if (!String.prototype.includes) {
  String.prototype.includes = function (searchString, position) {
    position = position || 0;
    return this.indexOf(searchString, position) !== -1;
  };
}

if (!Object.values) {
  Object.values = function (obj) {
    var vals = [];
    for (var key in obj) {
      if (obj.hasOwnProperty(key)) {
        vals.push(obj[key]);
      }
    }
    return vals;
  };
}

if (!Object.entries) {
  Object.entries = function (obj) {
    var entries = [];
    for (var key in obj) {
      if (obj.hasOwnProperty(key)) {
        entries.push([key, obj[key]]);
      }
    }
    return entries;
  };
}

if (Object.assign === undefined) {
  Object.assign = function (target) {
    if (target === undefined || target === null) {
      throw new TypeError("Cannot convert first argument to object");
    }

    var to = Object(target);
    for (var i = 1; i < arguments.length; i++) {
      var nextSource = arguments[i];
      if (nextSource === undefined || nextSource === null) {
        continue;
      }

      var keysArray = Object.keys(Object(nextSource));
      for (
        var nextIndex = 0, len = keysArray.length;
        nextIndex < len;
        nextIndex++
      ) {
        var nextKey = keysArray[nextIndex];
        var desc = Object.getOwnPropertyDescriptor(nextSource, nextKey);
        if (desc !== undefined && desc.enumerable) {
          to[nextKey] = nextSource[nextKey];
        }
      }
    }
    return to;
  };
}

if (!Object.keys) {
  Object.keys = function (obj) {
    var keys = [];
    for (var key in obj) {
      if (obj.hasOwnProperty(key)) {
        keys.push(key);
      }
    }
    return keys;
  };
}

if (!Array.prototype.includes) {
  Array.prototype.includes = function (searchElement, fromIndex) {
    if (this == null) {
      throw new TypeError('"this" is null or not defined');
    }

    var o = Object(this);
    var len = o.length >>> 0;
    if (len === 0) {
      return false;
    }

    var n = fromIndex | 0;
    var k = Math.max(n >= 0 ? n : len - Math.abs(n), 0);

    function sameValueZero(x, y) {
      return (
        x === y ||
        (typeof x === "number" && typeof y === "number" && isNaN(x) && isNaN(y))
      );
    }

    while (k < len) {
      if (sameValueZero(o[k], searchElement)) {
        return true;
      }
      k++;
    }
    return false;
  };
}

var WeakSet =
  WeakSet ||
  function () {
    this._items = [];
    this.add = function (value) {
      if (typeof value !== "object" && typeof value !== "function") {
        throw new TypeError("Invalid value used in weak set");
      }
      if (this._items.indexOf(value) === -1) {
        this._items.push(value);
      }
      return this;
    };
    this.delete = function (value) {
      var index = this._items.indexOf(value);
      if (index !== -1) {
        this._items.splice(index, 1);
        return true;
      }
      return false;
    };
    this.has = function (value) {
      return this._items.indexOf(value) !== -1;
    };
  };

if (typeof cc !== "undefined") {
  var _windowTimeIntervalId = 0;
  var _windowTimeFunHash = {};
  var WindowTimeFun = cc.Class.extend({
    _code: null,
    _intervalId: 0,
    ctor: function (code) {
      this._intervalId = _windowTimeIntervalId++;
      this._code = code;
    },
    fun: function () {
      if (!this._code) return;
      var code = this._code;
      if (typeof code == "string") {
        Function(code)();
      } else if (typeof code == "function") {
        code.apply(null, this._args);
      }
    },
  });

  /**
 * overwrite window's setTimeout
 @param {String|Function} code
 @param {number} delay
 @return {number}
 */
  var setTimeout = function (code, delay) {
    var target = new WindowTimeFun(code);
    if (arguments.length > 2)
      target._args = Array.prototype.slice.call(arguments, 2);
    var original = target.fun;
    target.fun = function () {
      original.apply(this, arguments);
      clearTimeout(target._intervalId);
    };
    cc.director
      .getScheduler()
      .schedule(
        target.fun,
        target,
        delay / 1000,
        0,
        0,
        false,
        target._intervalId + ""
      );
    _windowTimeFunHash[target._intervalId] = target;
    return target._intervalId;
  };

  /**
 * overwrite window's setInterval
 @param {String|Function} code
 @param {number} delay
 @return {number}
 */
  var setInterval = function (code, delay) {
    var target = new WindowTimeFun(code);
    if (arguments.length > 2)
      target._args = Array.prototype.slice.call(arguments, 2);
    cc.director
      .getScheduler()
      .schedule(
        target.fun,
        target,
        delay / 1000,
        cc.REPEAT_FOREVER,
        0,
        false,
        target._intervalId + ""
      );
    _windowTimeFunHash[target._intervalId] = target;
    return target._intervalId;
  };

  /**
 * overwrite window's clearInterval
 @param {number} intervalId
 */
  var clearInterval = function (intervalId) {
    var target = _windowTimeFunHash[intervalId];
    if (target) {
      cc.director.getScheduler().unschedule(target._intervalId + "", target);
      delete _windowTimeFunHash[intervalId];
    }
  };
  var clearTimeout = clearInterval;
}

// Promise Polyfill Implementation
// This polyfill provides basic Promise functionality for environments that don't support it

var Promise =
  Promise ||
  (function () {
    // Don't overwrite native Promise if it exists
    if (typeof Promise !== "undefined") {
      return;
    }

    // Promise states
    const PENDING = "pending";
    const FULFILLED = "fulfilled";
    const REJECTED = "rejected";

    function PromisePF(executor) {
      if (typeof executor !== "function") {
        throw new TypeError("Promise executor must be a function");
      }

      this.state = PENDING;
      this.value = undefined;
      this.reason = undefined;
      this.onFulfilledCallbacks = [];
      this.onRejectedCallbacks = [];

      const resolve = (value) => {
        if (this.state === PENDING) {
          this.state = FULFILLED;
          this.value = value;
          this.onFulfilledCallbacks.forEach((callback) => callback(value));
        }
      };

      const reject = (reason) => {
        if (this.state === PENDING) {
          this.state = REJECTED;
          this.reason = reason;
          this.onRejectedCallbacks.forEach((callback) => callback(reason));
        }
      };

      try {
        executor(resolve, reject);
      } catch (error) {
        reject(error);
      }
    }

    PromisePF.prototype.then = function (onFulfilled, onRejected) {
      return new PromisePF((resolve, reject) => {
        const handleFulfilled = (value) => {
          try {
            if (typeof onFulfilled === "function") {
              const result = onFulfilled(value);
              if (result && typeof result.then === "function") {
                // If result is a thenable, chain it
                result.then(resolve, reject);
              } else {
                resolve(result);
              }
            } else {
              resolve(value);
            }
          } catch (error) {
            reject(error);
          }
        };

        const handleRejected = (reason) => {
          try {
            if (typeof onRejected === "function") {
              const result = onRejected(reason);
              if (result && typeof result.then === "function") {
                // If result is a thenable, chain it
                result.then(resolve, reject);
              } else {
                resolve(result);
              }
            } else {
              reject(reason);
            }
          } catch (error) {
            reject(error);
          }
        };

        if (this.state === FULFILLED) {
          // Execute asynchronously
          setTimeout(() => handleFulfilled(this.value), 0);
        } else if (this.state === REJECTED) {
          // Execute asynchronously
          setTimeout(() => handleRejected(this.reason), 0);
        } else {
          // Promise is still pending, add callbacks
          this.onFulfilledCallbacks.push(handleFulfilled);
          this.onRejectedCallbacks.push(handleRejected);
        }
      });
    };

    PromisePF.prototype.catch = function (onRejected) {
      return this.then(null, onRejected);
    };

    PromisePF.prototype.finally = function (onFinally) {
      return this.then(
        (value) => PromisePF.resolve(onFinally()).then(() => value),
        (reason) =>
          PromisePF.resolve(onFinally()).then(() => {
            throw reason;
          })
      );
    };

    // Static methods
    PromisePF.resolve = function (value) {
      if (value && typeof value.then === "function") {
        return value;
      }
      return new PromisePF((resolve) => resolve(value));
    };

    PromisePF.reject = function (reason) {
      return new PromisePF((resolve, reject) => reject(reason));
    };

    PromisePF.all = function (promises) {
      return new PromisePF((resolve, reject) => {
        if (!Array.isArray(promises)) {
          reject(new TypeError("Promise.all expects an array"));
          return;
        }

        if (promises.length === 0) {
          resolve([]);
          return;
        }

        const results = [];
        let completedCount = 0;

        promises.forEach((promise, index) => {
          PromisePF.resolve(promise).then(
            (value) => {
              results[index] = value;
              completedCount++;
              if (completedCount === promises.length) {
                resolve(results);
              }
            },
            (reason) => reject(reason)
          );
        });
      });
    };

    PromisePF.race = function (promises) {
      return new PromisePF((resolve, reject) => {
        if (!Array.isArray(promises)) {
          reject(new TypeError("Promise.race expects an array"));
          return;
        }

        promises.forEach((promise) => {
          PromisePF.resolve(promise).then(resolve, reject);
        });
      });
    };

    PromisePF.allSettled = function (promises) {
      return new PromisePF((resolve) => {
        if (!Array.isArray(promises)) {
          resolve([]);
          return;
        }

        if (promises.length === 0) {
          resolve([]);
          return;
        }

        const results = [];
        let completedCount = 0;

        promises.forEach((promise, index) => {
          PromisePF.resolve(promise).then(
            (value) => {
              results[index] = { status: "fulfilled", value };
              completedCount++;
              if (completedCount === promises.length) {
                resolve(results);
              }
            },
            (reason) => {
              results[index] = { status: "rejected", reason };
              completedCount++;
              if (completedCount === promises.length) {
                resolve(results);
              }
            }
          );
        });
      });
    };

    // Make Promise available globally

    // Example usage and tests
    console.log(
      "---------------------------------------------- Promise polyfill loaded"
    );

    // Test basic functionality
    const testPromise = new PromisePF((resolve, reject) => {
      setTimeout(
        () =>
          resolve(
            "---------------------------------------------- Hello, Promise!"
          ),
        1000
      );
    });

    testPromise
      .then((value) => {
        console.log(
          "---------------------------------------------- Resolved:",
          value
        );
        return value.toUpperCase();
      })
      .then((value) => {
        console.log(
          "---------------------------------------------- Chained:",
          value
        );
      })
      .catch((error) => {
        console.error(
          "---------------------------------------------- Error:",
          error
        );
      });

    // Test Promise.all
    PromisePF.all([
      PromisePF.resolve(1),
      PromisePF.resolve(2),
      PromisePF.resolve(3),
    ]).then((values) => {
      cc.log(
        "---------------------------------------------- Promise.all result:",
        values
      ); // [1, 2, 3]
    });
    return PromisePF;
  })();
