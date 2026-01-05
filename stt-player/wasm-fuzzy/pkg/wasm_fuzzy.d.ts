/* tslint:disable */
/* eslint-disable */

export function are_words_similar(word1: string, word2: string, threshold: number): boolean;

export function calculate_combined_similarity(transcript: string, chunk: string): number;

export function levenshtein_distance(str1: string, str2: string): number;

export function n_gram_similarity(text1: string, text2: string, n: number): number;

export function normalize_text(text: string): string;

export function string_similarity(str1: string, str2: string): number;

export function word_level_similarity(transcript: string, chunk: string): number;

export function word_to_phrase_similarity(word: string, phrase: string): number;

export type InitInput = RequestInfo | URL | Response | BufferSource | WebAssembly.Module;

export interface InitOutput {
  readonly memory: WebAssembly.Memory;
  readonly are_words_similar: (a: number, b: number, c: number, d: number, e: number) => number;
  readonly calculate_combined_similarity: (a: number, b: number, c: number, d: number) => number;
  readonly levenshtein_distance: (a: number, b: number, c: number, d: number) => number;
  readonly n_gram_similarity: (a: number, b: number, c: number, d: number, e: number) => number;
  readonly normalize_text: (a: number, b: number) => [number, number];
  readonly string_similarity: (a: number, b: number, c: number, d: number) => number;
  readonly word_level_similarity: (a: number, b: number, c: number, d: number) => number;
  readonly word_to_phrase_similarity: (a: number, b: number, c: number, d: number) => number;
  readonly __wbindgen_externrefs: WebAssembly.Table;
  readonly __wbindgen_malloc: (a: number, b: number) => number;
  readonly __wbindgen_realloc: (a: number, b: number, c: number, d: number) => number;
  readonly __wbindgen_free: (a: number, b: number, c: number) => void;
  readonly __wbindgen_start: () => void;
}

export type SyncInitInput = BufferSource | WebAssembly.Module;

/**
* Instantiates the given `module`, which can either be bytes or
* a precompiled `WebAssembly.Module`.
*
* @param {{ module: SyncInitInput }} module - Passing `SyncInitInput` directly is deprecated.
*
* @returns {InitOutput}
*/
export function initSync(module: { module: SyncInitInput } | SyncInitInput): InitOutput;

/**
* If `module_or_path` is {RequestInfo} or {URL}, makes a request and
* for everything else, calls `WebAssembly.instantiate` directly.
*
* @param {{ module_or_path: InitInput | Promise<InitInput> }} module_or_path - Passing `InitInput` directly is deprecated.
*
* @returns {Promise<InitOutput>}
*/
export default function __wbg_init (module_or_path?: { module_or_path: InitInput | Promise<InitInput> } | InitInput | Promise<InitInput>): Promise<InitOutput>;
