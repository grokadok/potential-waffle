//faster quicksort using a stack to eliminate recursion, sorting inplace to reduce memory usage, and using insertion sort for small partition sizes
/**
 * Sorter using QuickSort algorithm, adapted to also handle sort by object attribute, by array item and consecutive sorting.
 * @param {Array} ary - Array to sort.
 * @param {[Number,Number|String][]|Number} [options=0] - Number (0: asc, 1: desc) for simple sorting, array for consecutive objects/arrays sorting.
 * @returns sorted array.
 */
function quicksort(ary, options = 0) {
    if (ary.length > 1) {
        const start = performance.now(),
            type = typeof ary[0],
            shell_sort_bound = type === "object" ? arr_ssb : num_ssb,
            inplace_quicksort_partition = type === "object" ? arr_iqp : num_iqp,
            insertion_sort = type === "object" ? arr_is : num_is;

        let stack = [],
            entry = [
                0,
                ary.length,
                2 * Math.floor(Math.log(ary.length) / Math.log(2)),
            ];
        stack.push(entry);
        while (stack.length > 0) {
            entry = stack.pop();
            const start = entry[0],
                end = entry[1],
                pivot = Math.round((start + end) / 2);
            let depth = entry[2];
            if (depth === 0) {
                ary = shell_sort_bound(ary, start, end, options);
                continue;
            }
            depth--;

            const pivotNewIndex = inplace_quicksort_partition(
                ary,
                start,
                end,
                pivot,
                options
            );
            if (end - pivotNewIndex > 16) {
                entry = [pivotNewIndex, end, depth];
                stack.push(entry);
            }
            if (pivotNewIndex - start > 16) {
                entry = [start, pivotNewIndex, depth];
                stack.push(entry);
            }
        }
        ary = insertion_sort(ary, options);

        console.log(performance.now() - start + "ms quicksort");
    }
    return ary;
}
/**
 * Compares two objects/arrays according to one or more values.
 * @param {*} a
 * @param {*} b
 * @param {[Number,Number|String][]} param - Paremeters
 * @returns {Boolean} Result of the comparison.
 */
function arrCompare(a, b, param) {
    for (let i = 0; i < param.length; i++) {
        // let c, d;
        // switch (typeof a[param[i][1]]) {
        //     case "object":
        //         c = a[param[i][1]][0];
        //         d = b[param[i][1]][0];
        //         break;
        //     case "string":
        //         c = a[param[i][1]].toLocaleLowerCase();
        //         d = b[param[i][1]].toLocaleLowerCase();
        //     default:
        //         c = a[param[i][1]];
        //         d = b[param[i][1]];
        // }
        let c, d;
        if (a[param[i][1]])
            c =
                typeof a[param[i][1]] === "object"
                    ? a[param[i][1]][0]
                    : a[param[i][1]];
        else c = "";
        if (b[param[i][1]])
            d =
                typeof b[param[i][1]] === "object"
                    ? b[param[i][1]][0]
                    : b[param[i][1]];
        else d = "";
        c = typeof c === "string" ? c.toLowerCase() : c;
        d = typeof d === "string" ? d.toLowerCase() : d;
        if (c === d) continue;
        else return param[i][0] === 0 ? c > d : d > c;
    }
    // if (a[param[0][1]] === b[param[0][1]]) {
    //     if (a[param[1][1]] === b[param[1][1]]) {
    //         if (a[param[2][1]] === b[param[2][1]])
    //             return a[param[3][1]] > b[param[3][1]];
    //         else return a[param[2][1]] > b[param[2][1]];
    //     } else return a[param[1][1]] > b[param[1][1]];
    // } else return a[param[0][1]] > b[param[0][1]];
}
function objCompare(a, b, param) {
    for (let i = 0; i < param.length; i++) {
        const key = param[i][1],
            dir = param[i][0];
        if (a[key] === b[key]) continue;
        else if (
            (dir === 0 && a[key] > b[key]) ||
            (dir === 1 && a[key] < b[key])
        )
            return true;
        return false;
    }
}
function num_ssb(ary, start, end, options) {
    let inc = Math.round((start + end) / 2),
        i,
        j,
        t;
    while (inc >= start) {
        for (i = inc; i < end; i++) {
            t = ary[i];
            j = i;
            if (options === 0) {
                while (j >= inc && ary[j - inc] > t) {
                    ary[j] = ary[j - inc];
                    j -= inc;
                }
            } else {
                while (j >= inc && ary[j - inc] < t) {
                    ary[j] = ary[j - inc];
                    j -= inc;
                }
            }
            ary[j] = t;
        }
        inc = Math.round(inc / 2.2);
    }
    return ary;
}
function arr_ssb(ary, start, end, options) {
    let inc = Math.round((start + end) / 2),
        i,
        j,
        t;
    while (inc >= start) {
        for (i = inc; i < end; i++) {
            t = ary[i];
            j = i;
            while (j >= inc && arrCompare(ary[j - inc], t, options)) {
                ary[j] = ary[j - inc];
                j -= inc;
            }
            ary[j] = t;
        }
        inc = Math.round(inc / 2.2);
    }
    return ary;
}
function obj_shell_sort_bound(ary, start, end, options) {
    let inc = Math.round((start + end) / 2),
        i,
        j,
        t;
    while (inc >= start) {
        for (i = inc; i < end; i++) {
            t = ary[i];
            j = i;
            while (j >= inc && objCompare(ary[j - inc], t, options)) {
                ary[j] = ary[j - inc];
                j -= inc;
            }
            ary[j] = t;
        }
        inc = Math.round(inc / 2.2);
    }
    return ary;
}

function num_iqp(ary, start, end, pivotIndex, options) {
    let i = start,
        j = end;
    const pivot = ary[pivotIndex];
    while (true) {
        if (options === 0) {
            while (ary[i] < pivot) {
                i++;
            }
            j--;
            while (pivot < ary[j]) {
                j--;
            }
        } else {
            while (ary[i] > pivot) {
                i++;
            }
            j--;
            while (pivot > ary[j]) {
                j--;
            }
        }
        if (!(i < j)) {
            return i;
        }
        swap(ary, i, j);
        i++;
    }
}
function arr_iqp(ary, start, end, pivotIndex, options) {
    let i = start,
        j = end;
    const pivot = ary[pivotIndex];
    while (true) {
        while (arrCompare(pivot, ary[i], options)) {
            i++;
        }
        j--;
        while (arrCompare(ary[j], pivot, options)) {
            j--;
        }
        if (!(i < j)) {
            return i;
        }
        swap(ary, i, j);
        i++;
    }
}
function obj_inplace_quicksort_partition(ary, start, end, pivotIndex, options) {
    let i = start,
        j = end;
    const pivot = ary[pivotIndex];
    while (true) {
        while (objCompare(pivot, ary[i], options)) {
            i++;
        }
        j--;
        while (objCompare(ary[j], pivot, options)) {
            j--;
        }
        if (!(i < j)) {
            return i;
        }
        swap(ary, i, j);
        i++;
    }
}

function num_is(ary, options, obj) {
    for (let i = 1, l = ary.length; i < l; i++) {
        const value = ary[i];
        let j;
        for (j = i - 1; j >= 0; j--) {
            if (options === 0 && ary[j] <= value) break;
            if (options === 1 && ary[j] >= value) break;
            ary[j + 1] = ary[j];
        }
        ary[j + 1] = value;
    }
    return ary;
}
function arr_is(ary, options) {
    for (let i = 1, l = ary.length; i < l; i++) {
        const value = ary[i];
        let j;
        for (j = i - 1; j >= 0; j--) {
            if (ary[j] === value || arrCompare(value, ary[j], options)) break;
            ary[j + 1] = ary[j];
        }
        ary[j + 1] = value;
    }
    return ary;
}
function obj_insertion_sort(ary, options) {
    for (let i = 1, l = ary.length; i < l; i++) {
        const value = ary[i];
        let j;
        for (j = i - 1; j >= 0; j--) {
            if (ary[j] === value || objCompare(value, ary[j], options)) break;
            ary[j + 1] = ary[j];
        }
        ary[j + 1] = value;
    }
    return ary;
}

function swap(ary, a, b) {
    const t = ary[a];
    ary[a] = ary[b];
    ary[b] = t;
}

// For testing purpose only, data generation and built-in sort() functions.
function dataGen(x, type) {
    let input = [];
    const randomString = (length) => {
        let result = "";
        const characters =
                "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789",
            charactersLength = characters.length;
        for (var i = 0; i < length; i++) {
            result += characters.charAt(
                Math.floor(Math.random() * charactersLength)
            );
        }
        return result;
    };
    for (var i = 0; i < x; i++) {
        if (type === 0) {
            input[i] = Math.round(Math.random() * 1000000);
        } else if (type === 1) {
            input[i] = {
                id: Math.round(Math.random() * 1000000),
                simpal: randomString(1),
                dual: randomString(2),
                quintal: randomString(5),
                tenal: randomString(10),
            };
        } else if (type === 2) {
            input[i] = [
                Math.round(Math.random() * 1000000),
                randomString(1),
                randomString(2),
                randomString(5),
                randomString(10),
            ];
        } else if (type === 3)
            input[i] = [
                Math.round(Math.random() * 10),
                Math.round(Math.random() * 10),
                Math.round(Math.random() * 10),
                Math.round(Math.random() * 10),
                Math.round(Math.random() * 10),
            ];
    }
    return input;
}

function builtInSortNum(ary) {
    const a = performance.now();
    ary.sort((a, b) => a - b);
    console.log(performance.now() - a);
}
function builtInSortArr(ary, key1, key2, key3, key4) {
    const a = performance.now();
    ary.sort((a, b) => {
        if (a[key1] === b[key1]) {
            if (a[key2] === b[key2]) {
                if (a[key3] === b[key3]) return a[key4] > b[key4];
                else return a[key3] > b[key3];
            } else return a[key2] > b[key2];
        } else return a[key1] > b[key1];
    });
    console.log(performance.now() - a);
}
