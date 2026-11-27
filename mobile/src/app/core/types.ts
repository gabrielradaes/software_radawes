export interface Me {
  user: { id: number; name: string; first_name: string; role: string; branch_id: number | null; scoped: boolean } | null;
  can?: { stock: boolean; sell: boolean; entry: boolean; new_model: boolean; publish: boolean; teach: boolean };
  branches?: { id: number; name: string; full: string }[];
  payments?: { key: string; label: string }[];
  classes?: { id: number; name: string }[];
  brands?: string[];
  colors?: string[];
  links?: { panel: string; publish: string; teach: string };
}

export interface ModelHit { id: number; code: string | null; name: string; price: number; available: number; image: string | null }

export interface Cell { variant_id: number; sku: string; price: number; available: number; reserved: number }

export interface ModelGrid {
  product: { id: number; code: string | null; name: string; price: number; image: string | null; category: string | null; edit: string | null };
  branch_id: number;
  branches: { id: number; name: string }[];
  colors: { name: string; hex: string | null }[];
  sizes: string[];
  cells: Record<string, Record<string, Record<string, Cell>>>;
}

export interface ChatMessage {
  id?: number;
  role: 'user' | 'assistant';
  text: string;
  table?: [string[], string[][]] | null;
  links?: [string, string][];
  confirm?: boolean;
  choices?: [string, string][];
  time?: string;
}
