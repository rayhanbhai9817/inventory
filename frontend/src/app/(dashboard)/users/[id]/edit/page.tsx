import { UserEditForm } from "./UserEditForm";

export default async function EditUserPage(props: PageProps<"/users/[id]/edit">) {
  const { id } = await props.params;

  return <UserEditForm id={Number(id)} />;
}
