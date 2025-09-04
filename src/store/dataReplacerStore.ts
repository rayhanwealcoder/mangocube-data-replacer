import { create } from 'zustand';

interface SearchResult {
  total: number;
  total_pages: number;
  page: number;
  per_page: number;
  rows: Array<{
    post_id: number;
    post_title: string;
    post_type: string;
    meta_key: string;
    meta_value: string;
    has_backup: boolean;
    is_modified?: boolean;
  }>;
}

interface PostType {
  value: string;
  label: string;
  count: number;
}

interface DataReplacerState {
  searchResults: SearchResult | null;
  isLoading: boolean;
  error: string | null;
  postTypes: PostType[];
  metaKeys: string[];
  
  // Actions
  searchMeta: (filters: any) => Promise<SearchResult>;
  getPostTypes: () => Promise<void>;
  getMetaKeys: (postType?: string) => Promise<void>;
  updateRow: (data: any) => Promise<void>;
  restoreRow: (postId: number, metaKey: string) => Promise<void>;
  previewReplace: (params: any) => Promise<any>;
  executeReplace: (params: any) => Promise<any>;
  initializeStore: () => Promise<void>;
}

export const useDataReplacerStore = create<DataReplacerState>((set, get) => ({
  searchResults: null,
  isLoading: false,
  error: null,
  postTypes: [],
  metaKeys: [],

  searchMeta: async (filters: any) => {
    set({ isLoading: true, error: null });
    
    try {
      // Real AJAX call to WordPress
      const response = await fetch(wcfdr_ajax.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wcfdr_search',
          nonce: wcfdr_ajax.nonce,
          ...filters
        })
      });

      if (!response.ok) {
        throw new Error('Search request failed');
      }

      const data = await response.json();
      
      if (data.success) {
        set({ searchResults: data.data, isLoading: false });
        return data.data;
      } else {
        throw new Error(data.data || 'Search failed');
      }
    } catch (error) {
      set({ error: 'Search failed', isLoading: false });
      throw error;
    }
  },

  getPostTypes: async () => {
    try {
      // Real AJAX call to get post types
      const response = await fetch(wcfdr_ajax.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wcfdr_get_post_types',
          nonce: wcfdr_ajax.nonce
        })
      });

      if (!response.ok) {
        throw new Error('Failed to fetch post types');
      }

      const data = await response.json();
      
      if (data.success) {
        set({ postTypes: data.data });
      } else {
        throw new Error(data.data || 'Failed to load post types');
      }
    } catch (error) {
      console.error('Failed to load post types:', error);
      // Fallback to default post types if AJAX fails
      const defaultPostTypes: PostType[] = [
        { value: 'post', label: 'Posts', count: 0 },
        { value: 'page', label: 'Pages', count: 0 },
        { value: 'st-templates', label: 'ST Templates', count: 0 }
      ];
      set({ postTypes: defaultPostTypes });
    }
  },

  getMetaKeys: async (postType?: string) => {
    try {
      // Real AJAX call to get meta keys (postType is now optional)
      const response = await fetch(wcfdr_ajax.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wcfdr_get_meta_keys',
          nonce: wcfdr_ajax.nonce,
          ...(postType && { post_type: postType })
        })
      });

      if (!response.ok) {
        throw new Error('Failed to fetch meta keys');
      }

      const data = await response.json();
      
      if (data.success) {
        set({ metaKeys: data.data });
      } else {
        throw new Error(data.data || 'Failed to load meta keys');
      }
    } catch (error) {
      console.error('Failed to load meta keys:', error);
      // Fallback to common meta keys if AJAX fails
      const defaultMetaKeys = [
        '_thumbnail_id',
        '_wp_page_template',
        '_product_image_gallery',
        'custom_field_1',
        'custom_field_2',
        'seo_description',
        'seo_keywords'
      ];
      set({ metaKeys: defaultMetaKeys });
    }
  },

  updateRow: async (data: any) => {
    set({ isLoading: true });
    try {
      // Real AJAX call to update row
      const response = await fetch(wcfdr_ajax.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wcfdr_update_row',
          nonce: wcfdr_ajax.nonce,
          ...data
        })
      });

      if (!response.ok) {
        throw new Error('Update request failed');
      }

      const result = await response.json();
      
      if (!result.success) {
        throw new Error(result.data || 'Update failed');
      }

      set({ isLoading: false });
    } catch (error) {
      set({ error: 'Update failed', isLoading: false });
      throw error;
    }
  },

  restoreRow: async (postId: number, metaKey: string) => {
    set({ isLoading: true });
    try {
      // Real AJAX call to restore row
      const response = await fetch(wcfdr_ajax.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wcfdr_restore',
          nonce: wcfdr_ajax.nonce,
          post_id: postId.toString(),
          meta_key: metaKey
        })
      });

      if (!response.ok) {
        throw new Error('Restore request failed');
      }

      const result = await response.json();
      
      if (!result.success) {
        throw new Error(result.data || 'Restore failed');
      }

      set({ isLoading: false });
    } catch (error) {
      set({ error: 'Restore failed', isLoading: false });
      throw error;
    }
  },

  previewReplace: async (params: any) => {
    set({ isLoading: true, error: null });
    
    try {
      // Real AJAX call to preview replace
      const response = await fetch(wcfdr_ajax.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wcfdr_preview',
          nonce: wcfdr_ajax.nonce,
          ...params
        })
      });

      if (!response.ok) {
        throw new Error('Preview request failed');
      }

      const data = await response.json();
      
      if (data.success) {
        set({ isLoading: false });
        return data.data;
      } else {
        throw new Error(data.data || 'Preview failed');
      }
    } catch (error) {
      set({ error: 'Preview failed', isLoading: false });
      throw error;
    }
  },

  executeReplace: async (params: any) => {
    set({ isLoading: true, error: null });
    
    try {
      // Real AJAX call to execute replace
      const response = await fetch(wcfdr_ajax.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'wcfdr_replace',
          nonce: wcfdr_ajax.nonce,
          ...params
        })
      });

      if (!response.ok) {
        throw new Error('Replace request failed');
      }

      const data = await response.json();
      
      if (data.success) {
        set({ isLoading: false });
        return data.data;
      } else {
        throw new Error(data.data || 'Replace failed');
      }
    } catch (error) {
      set({ error: 'Replace failed', isLoading: false });
      throw error;
    }
  },

  initializeStore: async () => {
    try {
      await get().getPostTypes();
    } catch (error) {
      console.error('Failed to initialize store:', error);
    }
  }
}));
