import objectAssign from 'object-assign';

// ------------------------------------
// Constants
// ------------------------------------

export const WIDGET_IMPORTED = 'WIDGET_IMPORTED';
export const WIDGET_LOADING = 'WIDGET_LOADING';
export const WIDGET_LOADED = 'WIDGET_LOADED';
export const WIDGET_LOAD_FAILED = 'WIDGET_LOAD_FAILED';
export const WIDGET_POSTING = 'WIDGET_POSTING';
export const WIDGET_POST_FAILED = 'WIDGET_POST_FAILED';

// ------------------------------------
// Actions
// ------------------------------------

// Fired when widgets are ready
export function widgetImported (widgetName: string, widgetInit: object): Action {
  return { type: WIDGET_IMPORTED, widgetName: widgetName, widgetInit: widgetInit };
}

// Fired when individual widget fetching data
export function widgetLoading (widgetName: string): Action {
  return { type: WIDGET_LOADING, widgetName: widgetName };
}

// Fired when widget has data
export function widgetLoaded (widgetName: string, data: object): Action {
  return { type: WIDGET_LOADED, widgetName: widgetName, data: data };
}

// Fired when widget has data
export function widgetLoadFailed (widgetName: string, error: object): Action {
  return { type: WIDGET_LOAD_FAILED, widgetName: widgetName };
}

// Fired when individual widget fetching data
export function widgetPosting (widgetName: string): Action {
  return { type: WIDGET_POSTING, widgetName: widgetName };
}

// Fired when individual widget fetching data
export function widgetPostFailed (widgetName: string): Action {
  return { type: WIDGET_POST_FAILED, widgetName: widgetName };
}

// Fired when widget should get data
export function widgetLoadData (widgetName: string, url: string, processData: Function): Function {
  return (dispatch: Function) => {
    // Call loading action
    dispatch(widgetLoading(widgetName));
    // Load data
    return fetch(url + '&method=GET', {
      method: 'get',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    }).then((response: object) => {
      // Good?
      if (response.status >= 200 && response.status < 300) {
        return response.json();
        // @TODO handle Error
      } else {
        let error = new Error(response.statusText);
        error.response = response;
        error.error = response.statusText;
        return error;
      }
    }).then((json: object) => {
      if(json && !json.error) {
        const data = processData(json);
        // Call loaded action
        dispatch(widgetLoaded(widgetName, data));
      }
      else {
        console.log('post failed', json);
        dispatch(widgetLoadFailed(widgetName, json));
      }
    }).catch(function (error) {
      console.log('post failed', error);
      dispatch(widgetLoadFailed(widgetName, error));
    });
  };
}

// Fired when widget should get data
export function widgetPostData (widgetName: string, url: string, method: string = 'POST', data: object): Function {
  return (dispatch: Function) => {
    // Call ;posting action
    dispatch(widgetPosting(widgetName));
    // Compile post
    let form_data = new FormData();
    for(let key of Object.keys(data)) {
      form_data.append(key, data[key]);
    }
    // Load data
    return fetch(url + '&method=' + method, {
      method: 'post',
      body: form_data
    }).then((response: object) => {
      // Good?
      if (response.status >= 200 && response.status < 300) {
        return response.json();
        // @TODO handle Error
      } else {
        let error = new Error(response.statusText);
        error.response = response;
        error.error = response.statusText;
        return error;
      }
    }).then((json: object) => {
      console.log('hello');
      if(json && !json.error) {
        // Call loaded action
        // dispatch(widgetLoaded(widgetName, null));
        console.log('post success')
      }
      else {
        console.log('request failed', json);
        dispatch(widgetPostFailed(widgetName, json));
      }
    }).catch(function (error) {
      console.log('request failed', error);
      dispatch(widgetPostFailed(widgetName, error));
    });
  };
}

export function widgetPostAllData(widgetName: string, calls: Array): Function {
  return (dispatch: Function) => Promise.all(calls.map((call) => {
    return dispatch(widgetPostData(widgetName, call.url, call.method, call.data));
  }));
}

export const actions = {
  widgetImported,
  widgetLoading,
  widgetLoaded,
  widgetLoadData,
  widgetLoadFailed,
  widgetPostData,
  widgetPostAllData,
  widgetPostFailed
};

// ------------------------------------
// Action Handlers
// ------------------------------------
const ACTION_HANDLERS = {
  [WIDGET_IMPORTED]: (newState: object, action: {widgetName: string, widgetInit: object}): object => {
    // If nothing is passed, just do default
    if(!action.widgetInit) {
      action.widgetInit = {
        name: action.widgetName,
        status: 'init',
        data: {}
      }
    }
    newState.widgets[action.widgetName] = action.widgetInit;
    return newState;
  },
  [WIDGET_LOADING]: (newState: object, action: {widgetName: string}): object => {
    newState.widgets[action.widgetName] = objectAssign(
      {}, newState.widgets[action.widgetName], {'status': 'loading'}
    );
    return newState;
  },
  [WIDGET_LOADED]: (newState: object, action: {widgetName: string, data: object}): object => {
    let newWidget = {
      'status': 'loaded'
    };
    if(action.data) {
      newWidget.data = action.data
    }
    newState.widgets[action.widgetName] = objectAssign({}, newState.widgets[action.widgetName], newWidget);
    return newState;
  },
  [WIDGET_LOAD_FAILED]: (newState: object, action: {widgetName: string}): object => {
    // @TODO handle this
    let newWidget = {
      'status': 'load_failed'
    };
    newState.widgets[action.widgetName] = objectAssign({}, newState.widgets[action.widgetName], newWidget);
    return newState;
  },
  [WIDGET_POSTING]: (newState: object, action: {widgetName: string, data: object}): object => {
    newState.widgets[action.widgetName] = objectAssign(
      {}, newState.widgets[action.widgetName], {'status': 'posting'}
    );
    return newState;
  },
  [WIDGET_POST_FAILED]: (newState: object, action: {widgetName: string}): object => {
    // @TODO handle this
    let newWidget = {
      'status': 'post_failed'
    };
    newState.widgets[action.widgetName] = objectAssign({}, newState.widgets[action.widgetName], newWidget);
    return newState;
  },
};

// ------------------------------------
// Reducer
// ------------------------------------

const initialState = {
  widgets: {}
};

export default function widgetReducer (state: object = initialState, action: Action): object {
  const handler = ACTION_HANDLERS[action.type];
  if (handler) {
    let newState = objectAssign({}, state);
    return handler(newState, action);
  }
  return state;
}
