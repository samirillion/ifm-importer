import Vue from "vue";
import Vuex from "vuex";

Vue.use(Vuex);

const getDefaultState = () => {
  return {
    importOffset: 0,
    importLimit: 0,
    uploadedFileId: null,
    csvLength: 1,
    stepIdCount: 1,
    varIdCount: 1,
    import: {
      error: false,
      complete: false,
      progress: ""
    },
    steps: [
      {
        id: "create_post_1",
        action: "create_post",
        getMap: [{}],
        setMap: [{ left: "post_type" }]
      }
    ],
    csvFields: [],
    customVars: [
      {
        name: "Custom Var",
        id: 1,
        code: ""
      }
    ]
  };
};

const state = getDefaultState();

export default new Vuex.Store({
  state,
  getters: {
    customVars: state => {
      return state.customVars.map(customVar => {
        return {
          key: customVar.name,
          value: customVar.id
        };
      });
    },
    stepIds: state => {
      return state.steps.map(step => step.id);
    },
    jsonVars: state => {
      return JSON.stringify(state.customVars);
    },
    jsonSteps: state => {
      return JSON.stringify(state.steps);
    }
  },
  mutations: {
    updateOffset(state, value) {
      state.importOffset = value;
    },
    updateLimit(state, value) {
      state.importLimit = value;
    },
    incrementVarId(state) {
      state.varIdCount++;
    },
    setFileId(state, id) {
      state.uploadedFileId = id;
    },
    updateCsvFields(state, csvFields) {
      state.csvFields = csvFields;
    },
    addVar(state, index) {
      state.customVars.splice(index + 1, 0, {
        id: "",
        map: [{}]
      });
    },
    removeVar(state, varIndex) {
      state.customVars.splice(varIndex, 1);
    },
    addVarSegment(state, varArgs) {
      state.customVars[varArgs.varIndex].splice(
        varArgs.segmentLength + 1,
        0,
        {}
      );
    },
    removeVarSegment(state, varArgs) {},
    stepPresets: (state, obj) => {
      let index = obj.index;
      let setMap = [{}];
      let getMap = [{}];
      state.stepIdCount++;

      if (obj.setParams) {
        let setters = Object.keys(obj.setParams).filter(key =>
          /(required|recommended)/.test(obj.setParams[key])
        );
        if (setters.length > 0) {
          setMap = [];
          setters.forEach(param => {
            setMap.push({ left: param });
          });
        }
      }

      // populate get params
      if (obj.getParams) {
        let getters = Object.keys(obj.getParams).filter(
          key => obj.getParams[key].indexOf("required") !== -1
        );
        if (getters.length > 0) {
          getMap = [];
          getters.forEach(param => {
            getMap.push({ left: param });
          });
        }
      }

      state.steps[index].setMap = setMap;
      state.steps[index].getMap = getMap;
      state.steps[index].id =
        state.steps[index].action + "_" + state.stepIdCount;
    },
    addStep(state, stepLength) {
      state.stepIdCount++;
      state.steps.splice(stepLength + 1, 0, {
        id: "create_post_" + state.stepIdCount,
        action: "create_post",
        getMap: [{}],
        setMap: [{ left: "post_type" }]
      });
    },
    removeStep(state, stepIndex) {
      state.steps.splice(stepIndex, 1);
    },
    addMapRow(state, stepMapArgs) {
      if (stepMapArgs.isGetter) {
        state.steps[stepMapArgs.stepIndex].getMap.splice(
          stepMapArgs.mapLength + 1,
          0,
          {}
        );
      } else {
        state.steps[stepMapArgs.stepIndex].setMap.splice(
          stepMapArgs.mapLength + 1,
          0,
          {}
        );
      }
    },
    deleteMapRow(state, stepMapArgs) {
      if (stepMapArgs.isGetter) {
        state.steps[stepMapArgs.stepIndex].getMap.splice(
          stepMapArgs.mapIndex,
          1
        );
      } else {
        state.steps[stepMapArgs.stepIndex].setMap.splice(
          stepMapArgs.mapIndex,
          1
        );
      }
    }
  }
});
