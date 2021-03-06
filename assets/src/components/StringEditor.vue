<template>
  <!-- bidirectional data binding（双向数据绑定） -->
  <!-- <codemirror v-model="code" :options="cmOptions"></codemirror> -->
  <div class="string-editor-wrapper">
    <div class="string-editor-header">
      <div class="csv-value-dropdown" v-show="csvFields.length > 0">
        <button class="button-secondary" @click="toggleFields">
          Csv Value
        </button>
        <table class="form-table" v-show="fieldsToggled">
          <tr valign="top" v-for="(field, index) in csvFields" :key="index">
            <td scope="row" @click="insert('{{' + field + '}}')">
              {{ field }}
            </td>
          </tr>
        </table>
      </div>
      <button
        class="button-secondary"
        v-for="(stringFunc, funcIndex) in stringFunctions"
        :key="funcIndex"
        @click="insert(stringFunc.insert)"
        :title="stringFunc.description"
      >
        {{ stringFunc.name }}
      </button>
      <div class="csv-value-dropdown">
        <button class="button-secondary" @click="toggleFormats">
          Date Formats
        </button>
        <table class="form-table" v-show="formatsToggled">
          <tr valign="top" v-for="(format, index) in dateFormats" :key="index">
            <td scope="row" @click="insertFormat(format)">
              {{ format }}
            </td>
          </tr>
        </table>
      </div>
    </div>
    <codemirror
      ref="myCm"
      :value="customVar.code"
      :options="cmOptions"
      @ready="onCmReady"
      @focus="onCmFocus"
      @input="onCmCodeChange"
    ></codemirror>
    <div
      class="preview-var-wrapper"
      v-show="customVar.code && customVar.code.length"
    >
      <hr />
      <button class="button-secondary" @click="previewCustomVar">
        Preview
      </button>
      <span>for line #</span>
      <input type="number" v-model="previewRecordIndex" class="entry-length" />
      <div
        class="var-preview"
        v-show="customVarPreview && customVarPreview.length"
      >
        <button @click="customVarPreview = null">
          x
        </button>
        {{ customVarPreview }}
      </div>
    </div>
  </div>
</template>
<script>
import store from "@/store";

import { codemirror } from "vue-codemirror";
import "codemirror/lib/codemirror.css";
//custom codemirror language mode
import "@/utils/IfmMode";

import { stringFunctions, dateFormats } from "@/services/StringEdits";

import { WpApi } from "@/services/WpApi";

export default {
  components: {
    codemirror
  },
  props: {
    index: {
      type: Number
    }
  },
  data() {
    return {
      ref: this.index + 1,
      fieldsToggled: false,
      formatsToggled: false,
      dateFormats,
      stringFunctions,
      customVarPreview: null,
      previewRecordIndex: 1,
      cmOptions: {
        // codemirror options
        tabSize: 4,
        mode: "IfmScript",
        theme: "default",
        lineNumbers: true,
        line: true
        // more codemirror options, 更多 codemirror 的高级配置...
      }
    };
  },
  methods: {
    async previewCustomVar() {
      this.customVarPreview = null;
      this.customVarPreview = await WpApi.previewCustomVar()
        .param("upload_id", store.state.uploadedFileId)
        .param("record_index", Number(this.previewRecordIndex) - 1)
        .param("var_code", this.customVar.code);
    },
    insert(value) {
      this.codemirror.replaceSelection(value);
      this.codemirror.focus();
      this.fieldsToggled = false;
    },
    insertFormat(format) {
      this.codemirror.replaceSelection('"' + format + '"');
      this.codemirror.focus();
      this.formatsToggled = false;
    },
    toggleFields() {
      this.fieldsToggled = !this.fieldsToggled;
    },
    toggleFormats() {
      this.formatsToggled = !this.formatsToggled;
    },
    onCmReady(cm) {},
    onCmFocus(cm) {},
    onCmCodeChange(newCode) {
      store.state.customVars[this.index].code = newCode;
    }
  },
  computed: {
    customVar() {
      return store.state.customVars[this.index];
    },
    csvFields: {
      get() {
        return store.state.csvFields;
      }
    },
    codemirror() {
      return this.$refs.myCm.codemirror;
    }
  }
};
</script>
