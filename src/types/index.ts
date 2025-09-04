// WordPress Data Types
export interface WordPressData {
  apiUrl: string
  nonce: string
  strings: Record<string, string>
  settings: PluginSettings
  user: UserInfo
}

export interface PluginSettings {
  maxPerPage: number
  maxBulkRows: number
  regexTimeout: number
  backupRetention: number
  enableLiveTester: boolean
  enableAutoSuggest: boolean
  enableProgressBar: boolean
  enableKeyboardShortcuts: boolean
}

export interface UserInfo {
  id: number
  can: {
    edit_posts: boolean
    manage_options: boolean
  }
}

// Search Types
export interface SearchParams {
  post_type: string
  meta_key: string
  value?: string
  page?: number
  per_page?: number
  case_sensitive?: boolean
  regex?: boolean
  dry_run?: boolean
}

export interface SearchResult {
  post_id: number
  post_title: string
  post_type: string
  post_status: string
  meta_key: string
  meta_value: string
  meta_id: number
  has_backup: boolean
  backup_count: number
  edit_url: string
  view_url: string
}

export interface SearchResponse {
  rows: SearchResult[]
  total: number
  total_pages: number
  page: number
  per_page: number
  has_next_page: boolean
  has_prev_page: boolean
}

export interface MetaKeySuggestion {
  key: string
  frequency: number
  label: string
}

export interface PostTypeInfo {
  value: string
  label: string
  plural_label: string
  count: number
  supports: Record<string, boolean>
}

// Replace Types
export type ReplaceMode = 
  | 'plain'
  | 'plain_cs'
  | 'regex'
  | 'url'
  | 'url_segment'
  | 'prefix_swap'
  | 'full_text'

export interface ReplaceParams {
  find: string
  replace: string
  mode: ReplaceMode
  meta_key: string
  post_type: string
  value_filter?: string
  case_sensitive?: boolean
  limit?: number
  page?: number
  per_page?: number
  confirm?: boolean
  batch_id?: string
}

export interface ReplacePreview {
  rows: ReplacePreviewRow[]
  total: number
  total_matches: number
  mode: ReplaceMode
  find: string
  replace: string
  has_changes: boolean
}

export interface ReplacePreviewRow {
  post_id: number
  post_title: string
  meta_key: string
  meta_before: string
  meta_after: string
  match_count: number
  changes: ReplaceChanges
  warnings: string[]
}

export interface ReplaceChanges {
  type: string
  [key: string]: any
}

export interface ReplaceResult {
  ok: boolean
  updated: number
  failed: number
  items: ReplaceResultItem[]
  batch_id: string
  total_processed: number
}

export interface ReplaceResultItem {
  post_id: number
  status: 'updated' | 'failed' | 'skipped' | 'error'
  old_value?: string
  new_value?: string
  match_count?: number
  message?: string
}

// Backup Types
export interface BackupInfo {
  revision_id: string
  post_id: number
  meta_key: string
  old_value: string
  new_value: string
  operation: 'update' | 'restore' | 'manual'
  batch_id?: string
  actor_id: number
  actor_name: string
  created_at: string
  old_value_length: number
  new_value_length: number
  value_excerpt: string
  is_latest: boolean
}

export interface BackupParams {
  post_id: number
  meta_key: string
  old_value: string
  new_value?: string
  batch_id?: string
  operation?: 'update' | 'restore' | 'manual'
}

export interface RestoreParams {
  revision_id?: string
  post_id?: number
  meta_key?: string
  latest?: boolean
}

export interface BackupStats {
  total_backups: number
  total_size: number
  oldest_backup: string | null
  newest_backup: string | null
  backups_today: number
  backups_this_week: number
}

// API Response Types
export interface ApiResponse<T = any> {
  success: boolean
  data?: T
  message?: string
  code?: string
}

export interface ApiError {
  success: false
  message: string
  code: string
  details?: any
}

// UI Component Types
export interface ToastProps {
  title: string
  description?: string
  type: 'success' | 'error' | 'warning' | 'info'
  duration?: number
}

export interface DialogProps {
  isOpen: boolean
  onClose: () => void
  title: string
  children: React.ReactNode
  size?: 'sm' | 'md' | 'lg' | 'xl' | 'full'
}

export interface ModalProps extends DialogProps {
  onConfirm?: () => void
  confirmText?: string
  cancelText?: string
  isDestructive?: boolean
}

export interface TableColumn<T> {
  key: keyof T
  label: string
  sortable?: boolean
  width?: string | number
  render?: (value: any, row: T) => React.ReactNode
}

export interface PaginationProps {
  currentPage: number
  totalPages: number
  onPageChange: (page: number) => void
  showPageNumbers?: boolean
  showPageSize?: boolean
  pageSize?: number
  onPageSizeChange?: (size: number) => void
}

// Form Types
export interface FormField {
  name: string
  label: string
  type: 'text' | 'textarea' | 'select' | 'checkbox' | 'radio' | 'number' | 'email' | 'url'
  required?: boolean
  placeholder?: string
  options?: Array<{ value: string; label: string }>
  validation?: ValidationRule[]
  helpText?: string
}

export interface ValidationRule {
  type: 'required' | 'min' | 'max' | 'pattern' | 'custom'
  value?: any
  message: string
}

// Store Types
export interface SearchState {
  searchResults: SearchResponse
  isLoading: boolean
  searchError: string | null
  searchParams: SearchParams
  metaKeys: MetaKeySuggestion[]
  postTypes: PostTypeInfo[]
}

export interface ReplaceState {
  replaceMode: ReplaceMode
  findValue: string
  replaceValue: string
  caseSensitive: boolean
  limit: number | null
  isPreviewMode: boolean
  previewResults: ReplacePreview | null
}

export interface UIState {
  sidebarOpen: boolean
  liveTesterOpen: boolean
  selectedRows: SearchResult[]
  toastNotifications: ToastProps[]
  modals: ModalProps[]
}

// Utility Types
export type DeepPartial<T> = {
  [P in keyof T]?: T[P] extends object ? DeepPartial<T[P]> : T[P]
}

export type Optional<T, K extends keyof T> = Omit<T, K> & Partial<Pick<T, K>>

export type RequiredFields<T, K extends keyof T> = T & Required<Pick<T, K>>

// Event Types
export interface SearchEvent {
  type: 'search'
  params: SearchParams
  results: SearchResponse
  timestamp: number
}

export interface ReplaceEvent {
  type: 'replace'
  params: ReplaceParams
  results: ReplaceResult
  timestamp: number
}

export interface BackupEvent {
  type: 'backup' | 'restore'
  params: BackupParams | RestoreParams
  results: any
  timestamp: number
}

export type AppEvent = SearchEvent | ReplaceEvent | BackupEvent

// Live Tester Types
export interface LiveTesterState {
  sampleText: string
  findPattern: string
  replacePattern: string
  mode: ReplaceMode
  caseSensitive: boolean
  limit: number | null
  results: LiveTesterResult
  isProcessing: boolean
}

export interface LiveTesterResult {
  matches: MatchInfo[]
  newText: string
  matchCount: number
  warnings: string[]
  processingTime: number
}

export interface MatchInfo {
  start: number
  end: number
  text: string
  replacement: string
}

// Keyboard Shortcuts
export interface KeyboardShortcut {
  key: string
  ctrl?: boolean
  shift?: boolean
  alt?: boolean
  action: string
  description: string
}

// Performance Types
export interface PerformanceMetrics {
  searchTime: number
  replaceTime: number
  backupTime: number
  memoryUsage: number
  databaseQueries: number
}

// Error Types
export interface AppError extends Error {
  code: string
  details?: any
  timestamp: number
  userId?: number
  context?: Record<string, any>
}

// Configuration Types
export interface AppConfig {
  version: string
  environment: 'development' | 'staging' | 'production'
  debug: boolean
  features: {
    liveTester: boolean
    autoSuggest: boolean
    progressBar: boolean
    keyboardShortcuts: boolean
    backupRestore: boolean
    bulkOperations: boolean
  }
  limits: {
    maxPerPage: number
    maxBulkRows: number
    regexTimeout: number
    backupRetention: number
  }
}
