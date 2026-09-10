import { ProductEditForm } from "./ProductEditForm";

export default async function EditProductPage(props: PageProps<"/products/[id]/edit">) {
  const { id } = await props.params;

  return <ProductEditForm id={Number(id)} />;
}
